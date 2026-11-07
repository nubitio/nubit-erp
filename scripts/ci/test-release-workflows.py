"""Contract for release, image, and deploy workflow wiring.

Loads the workflow YAML. Does not call GitHub, GHCR, or Portainer.
"""

from __future__ import annotations

from pathlib import Path
import unittest

import yaml

ROOT = Path(__file__).resolve().parents[2]
WORKFLOWS = ROOT / '.github' / 'workflows'


def workflow_run_migrations(explicit: bool | None) -> str:
    """Mirror ``${{ inputs.run_migrations && 'true' || 'false' }}``.

    An omitted boolean input uses the workflow default, which is true.
    """
    value = True if explicit is None else explicit
    return 'true' if value else 'false'


def triggers(document: dict) -> dict:
    # PyYAML 1.1 parses the workflow key `on` as boolean true.
    raw = document.get(True, document.get('on'))
    if not isinstance(raw, dict):
        raise AssertionError(f'workflow triggers were {raw!r}')
    return raw


def load(name: str) -> dict:
    with (WORKFLOWS / name).open(encoding='utf-8') as handle:
        document = yaml.safe_load(handle)
    if not isinstance(document, dict):
        raise AssertionError(f'{name} did not parse as a mapping')
    return document


def workflow_files() -> list[Path]:
    return sorted(WORKFLOWS.glob('*.yml'))


class MigrationFlagTest(unittest.TestCase):
    def test_omitted_flag_keeps_forward_migrations(self):
        self.assertEqual(workflow_run_migrations(None), 'true')
        self.assertEqual(workflow_run_migrations(True), 'true')

    def test_explicit_false_skips_forward_migrations(self):
        self.assertEqual(workflow_run_migrations(False), 'false')


class ReleaseWorkflowTest(unittest.TestCase):
    def test_main_release_versions_and_builds_without_deploying(self):
        release = load('release.yml')
        self.assertEqual(list(triggers(release)), ['workflow_call'])
        jobs = release['jobs']
        self.assertEqual(set(jobs), {'version', 'build', 'manifest'})
        self.assertNotIn('deploy', jobs)
        self.assertIn('publish.mjs', jobs['version']['steps'][-1]['run'])
        self.assertEqual(jobs['build']['if'], "needs.version.outputs.tag != ''")
        self.assertEqual(jobs['build']['uses'], './.github/workflows/build-image.yml')
        self.assertIn('deployment.json', jobs['manifest']['steps'][0]['run'])
        serialized = yaml.safe_dump(release)
        self.assertNotIn('environment: prod', serialized)
        self.assertNotIn('deploy.yml', serialized)

    def test_ci_still_calls_release_only_after_quality_gates_on_main(self):
        ci = load('ci-main.yml')
        release = ci['jobs']['release']
        self.assertEqual(
            release['if'],
            "github.event_name == 'push' && github.ref == 'refs/heads/main'",
        )
        self.assertEqual(release['uses'], './.github/workflows/release.yml')
        self.assertEqual(release['needs'], ['backend', 'frontend', 'e2e', 'deploy-tooling'])
        commands = [
            step.get('run')
            for step in ci['jobs']['deploy-tooling']['steps']
            if isinstance(step, dict)
        ]
        self.assertIn('python3 scripts/ci/test-release-pipeline.py', commands)
        self.assertIn('python3 scripts/ci/test-release-workflows.py', commands)

    def test_commit_type_ci_is_not_releasable(self):
        import json

        config = json.loads((ROOT / '.releaserc.json').read_text(encoding='utf-8'))
        rules = config['plugins'][0][1]['releaseRules']
        by_type = {rule['type']: rule['release'] for rule in rules if 'type' in rule}
        self.assertIs(by_type['ci'], False)
        self.assertEqual(by_type['fix'], 'patch')


class ProductionPromotionTest(unittest.TestCase):
    def test_published_release_no_longer_promotes(self):
        prod = load('deploy-prod.yml')
        prod_triggers = triggers(prod)
        self.assertEqual(list(prod_triggers), ['workflow_dispatch'])
        self.assertNotIn('release', prod_triggers)
        tag = prod_triggers['workflow_dispatch']['inputs']['release_tag']
        self.assertIs(tag['required'], True)
        self.assertNotIn('run_migrations', prod_triggers['workflow_dispatch']['inputs'])
        job = prod['jobs']['deploy-production']
        self.assertEqual(job['uses'], './.github/workflows/deploy.yml')
        self.assertEqual(job['with']['environment'], 'prod')
        self.assertEqual(job['with']['image_ref'], '${{ needs.resolve.outputs.image_ref }}')
        self.assertEqual(job['with']['ref'], '${{ needs.resolve.outputs.commit }}')
        self.assertNotIn('run_migrations', job['with'])
        self.assertNotIn('prod_migrations', job['with'])
        self.assertNotIn('build-image.yml', yaml.safe_dump(prod))

    def test_reusable_deploy_still_migrates_unless_the_caller_says_false(self):
        deploy = load('deploy.yml')
        inputs = triggers(deploy)['workflow_call']['inputs']
        self.assertEqual(set(inputs), {'environment', 'image_ref', 'ref', 'run_migrations'})
        self.assertIs(inputs['run_migrations']['default'], True)
        self.assertNotIn('prod_migrations', inputs)
        self.assertEqual(deploy['concurrency']['group'], 'deploy-${{ inputs.environment }}')
        self.assertIs(deploy['concurrency']['cancel-in-progress'], False)
        portainer = next(
            step for step in deploy['jobs']['deploy']['steps']
            if step.get('name') == 'Deploy stack in Portainer'
        )
        self.assertEqual(
            portainer['env']['RUN_MIGRATIONS'],
            "${{ inputs.run_migrations && 'true' || 'false' }}",
        )
        self.assertEqual(workflow_run_migrations(None), 'true')

    def test_only_manual_prod_callers_remain(self):
        prod_callers = []
        for path in workflow_files():
            document = load(path.name)
            for job in document.get('jobs', {}).values():
                if not isinstance(job, dict):
                    continue
                if job.get('uses') != './.github/workflows/deploy.yml':
                    continue
                environment = (job.get('with') or {}).get('environment')
                if environment == 'prod':
                    prod_callers.append(path.name)
        self.assertEqual(sorted(prod_callers), ['deploy-prod.yml', 'rollback-prod.yml'])
        for path in workflow_files():
            document = load(path.name)
            event = triggers(document)
            self.assertNotIn('release', event, path.name)
            self.assertNotIn('prod_migrations', yaml.safe_dump(document))
            if 'push' not in event:
                continue
            for job in document.get('jobs', {}).values():
                if not isinstance(job, dict):
                    continue
                if job.get('uses') != './.github/workflows/deploy.yml':
                    continue
                self.assertNotEqual(
                    (job.get('with') or {}).get('environment'),
                    'prod',
                    path.name,
                )

    def test_rollback_still_skips_migrations(self):
        rollback = load('rollback-prod.yml')
        self.assertEqual(list(triggers(rollback)), ['workflow_dispatch'])
        job = rollback['jobs']['rollback']
        self.assertEqual(job['with']['environment'], 'prod')
        self.assertIs(job['with']['run_migrations'], False)
        self.assertEqual(workflow_run_migrations(False), 'false')

    def test_dev_and_staging_callers_are_unchanged(self):
        dev = load('deploy-dev.yml')
        dev_triggers = triggers(dev)
        self.assertEqual(list(dev_triggers), ['workflow_dispatch', 'push'])
        self.assertEqual(dev_triggers['push']['branches'], ['main'])
        dev_job = dev['jobs']['deploy-dev']
        self.assertEqual(dev_job['with']['environment'], 'dev')
        self.assertNotIn('run_migrations', dev_job['with'])
        self.assertEqual(dev['jobs']['build-image']['uses'], './.github/workflows/build-image.yml')

        staging = load('deploy-staging.yml')
        self.assertEqual(triggers(staging)['push']['tags'], ['v*.*.*-rc*'])
        staging_job = staging['jobs']['deploy-staging']
        self.assertEqual(staging_job['with']['environment'], 'stg')
        self.assertNotIn('run_migrations', staging_job['with'])
        self.assertNotIn('build-image.yml', yaml.safe_dump(staging))

    def test_release_manifest_still_requires_an_immutable_digest(self):
        resolve = load('resolve-release.yml')
        script = resolve['jobs']['resolve']['steps'][0]['run']
        self.assertIn('schema_version == 1', script)
        self.assertIn('@sha256:', script)
        self.assertIn('prerelease == false', script)
        image = load('build-image.yml')
        publish = next(step for step in image['jobs']['build']['steps'] if step.get('id') == 'publish')
        self.assertIn('@${DIGEST}', publish['run'])
        tags = image['jobs']['build']['steps']
        metadata = next(step for step in tags if step.get('id') == 'meta')
        self.assertNotIn('latest', metadata['with']['tags'])


if __name__ == '__main__':
    unittest.main()
