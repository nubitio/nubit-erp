"""Contract for release, image, and deploy workflow wiring.

Loads the workflow YAML. Does not call GitHub, GHCR, or Portainer.
"""

from __future__ import annotations

import os
from pathlib import Path
import subprocess
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
        self.assertEqual(jobs['build']['needs'], 'version')
        self.assertEqual(jobs['build']['uses'], './.github/workflows/build-image.yml')
        self.assertEqual(jobs['manifest']['if'], "needs.version.outputs.tag != ''")
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
        self.assertEqual(release['needs'], 'e2e')
        commands = [
            step.get('run')
            for step in ci['jobs']['deploy-tooling']['steps']
            if isinstance(step, dict)
        ]
        self.assertIn('python3 scripts/ci/test-release-pipeline.py', commands)
        self.assertIn('python3 scripts/ci/test-release-workflows.py', commands)

    def test_main_does_not_repeat_required_pr_checks(self):
        jobs = load('ci-main.yml')['jobs']
        for job in ('backend', 'frontend', 'deploy-tooling'):
            with self.subTest(job=job):
                self.assertEqual(jobs[job]['if'], "github.event_name != 'push'")

        resolve = next(step for step in jobs['changes']['steps'] if step.get('id') == 'jobs')
        self.assertEqual(resolve['env']['FORCE_ALL'], "${{ github.event_name == 'workflow_dispatch' }}")

    def test_e2e_runs_after_merge_or_on_manual_dispatch_but_not_on_prs(self):
        ci = load('ci-main.yml')
        events = triggers(ci)
        self.assertEqual(events['push']['branches'], ['main'])
        self.assertIn('workflow_dispatch', events)
        e2e = ci['jobs']['e2e']
        self.assertEqual(e2e['needs'], 'changes')
        self.assertEqual(
            e2e['if'],
            "needs.changes.result == 'success' && (github.event_name == 'push' || github.event_name == 'workflow_dispatch')",
        )

    def test_commit_type_ci_is_not_releasable(self):
        import json

        config = json.loads((ROOT / '.releaserc.json').read_text(encoding='utf-8'))
        rules = config['plugins'][0][1]['releaseRules']
        by_type = {rule['type']: rule['release'] for rule in rules if 'type' in rule}
        self.assertIs(by_type['ci'], False)
        self.assertEqual(by_type['fix'], 'patch')


class RunnerSelectionTest(unittest.TestCase):
    def select(self, *, provider: str = '', heavy: str = 'blacksmith', light: str = 'github') -> dict[str, str]:
        env = {
            **os.environ,
            'CI_RUNNER_PROVIDER': provider,
            'CI_RUNNER_PROVIDER_HEAVY': heavy,
            'CI_RUNNER_PROVIDER_LIGHT': light,
        }
        result = subprocess.run(
            ['bash', str(ROOT / 'scripts/ci/select-runner.sh')],
            capture_output=True,
            text=True,
            env=env,
            check=True,
        )
        return dict(
            line.split('=', maxsplit=1)
            for line in result.stdout.splitlines()
            if '=' in line
        )

    def test_hybrid_uses_blacksmith_for_heavy_and_github_for_light_jobs(self):
        selected = self.select(provider='hybrid')
        self.assertEqual(selected['runner'], 'blacksmith-4vcpu-ubuntu-2404')
        self.assertEqual(selected['runner_light'], 'ubuntu-latest')
        self.assertEqual(selected['use_blacksmith_docker'], 'true')

    def test_workload_provider_variables_can_reverse_the_hybrid(self):
        selected = self.select(provider='hybrid', heavy='github', light='blacksmith')
        self.assertEqual(selected['runner'], 'ubuntu-latest')
        self.assertEqual(selected['runner_light'], 'blacksmith-2vcpu-ubuntu-2404')
        self.assertEqual(selected['use_blacksmith_docker'], 'false')

    def test_legacy_global_provider_still_overrides_both_workloads(self):
        selected = self.select(provider='github', heavy='blacksmith', light='blacksmith')
        self.assertEqual(selected['runner'], 'ubuntu-latest')
        self.assertEqual(selected['runner_light'], 'ubuntu-latest')
        self.assertEqual(selected['use_blacksmith_docker'], 'false')


class ProductionPromotionTest(unittest.TestCase):
    def test_production_promotion_can_select_a_tag_from_the_ref_picker(self):
        prod = load('deploy-prod.yml')
        prod_triggers = triggers(prod)
        self.assertEqual(list(prod_triggers), ['workflow_dispatch'])
        self.assertNotIn('release', prod_triggers)
        tag = prod_triggers['workflow_dispatch']['inputs']['release_tag']
        self.assertIs(tag['required'], False)
        self.assertEqual(tag['default'], '')
        self.assertNotIn('run_migrations', prod_triggers['workflow_dispatch']['inputs'])
        self.assertEqual(
            prod['jobs']['resolve']['with']['release_tag'],
            '${{ inputs.release_tag || github.ref_name }}',
        )
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
        inputs = triggers(rollback)['workflow_dispatch']['inputs']
        self.assertIs(inputs['release_tag']['required'], False)
        validate = rollback['jobs']['validate']['steps'][0]['env']['RELEASE_TAG']
        self.assertIn('github.ref_type == \'tag\'', validate)
        self.assertEqual(rollback['jobs']['resolve']['if'], "inputs.release_tag != '' || github.ref_type == 'tag'")
        job = rollback['jobs']['rollback']
        self.assertEqual(job['with']['environment'], 'prod')
        self.assertIs(job['with']['run_migrations'], False)
        self.assertEqual(workflow_run_migrations(False), 'false')

    def test_dev_deploy_follows_ci_and_uses_the_verified_digest(self):
        ci = load('ci-main.yml')
        dev = ci['jobs']['deploy-dev']
        self.assertEqual(dev['needs'], ['changes', 'release'])
        self.assertEqual(dev['if'], "github.event_name == 'push' && github.ref == 'refs/heads/main'")
        self.assertEqual(dev['uses'], './.github/workflows/deploy.yml')
        self.assertEqual(dev['with']['image_ref'], '${{ needs.release.outputs.image_ref }}')

        release = load('release.yml')
        self.assertEqual(
            release[True]['workflow_call']['outputs']['image_ref']['value'],
            '${{ jobs.build.outputs.image_ref }}',
        )
        self.assertNotIn('if', release['jobs']['build'])

        dev = load('deploy-dev.yml')
        dev_triggers = triggers(dev)
        self.assertEqual(list(dev_triggers), ['workflow_dispatch'])
        self.assertEqual(dev['jobs']['build-image']['uses'], './.github/workflows/build-image.yml')
        self.assertEqual(dev['jobs']['deploy-dev']['with']['environment'], 'dev')

        staging = load('deploy-staging.yml')
        self.assertEqual(triggers(staging)['push']['tags'], ['v*.*.*-rc*'])
        staging_job = staging['jobs']['deploy-staging']
        self.assertEqual(staging_job['with']['environment'], 'stg')
        self.assertNotIn('run_migrations', staging_job['with'])
        self.assertNotIn('build-image.yml', yaml.safe_dump(staging))

    def test_runner_provider_can_be_selected_by_workload_size(self):
        ci = load('ci-main.yml')
        runner_input = triggers(ci)['workflow_dispatch']['inputs']['runner_provider']
        self.assertEqual(runner_input['type'], 'choice')
        self.assertEqual(runner_input['options'], ['hybrid', 'github', 'blacksmith', 'auto'])
        self.assertEqual(ci['env']['CI_RUNNER_PROVIDER'], "${{ github.event.inputs.runner_provider || vars.CI_RUNNER_PROVIDER || '' }}")

        changes = ci['jobs']['changes']
        self.assertEqual(changes['runs-on'], ['self-hosted', 'nubit-x64'])
        runner_env = next(step['env'] for step in changes['steps'] if step.get('id') == 'runner')
        self.assertEqual(runner_env['CI_RUNNER_PROVIDER_HEAVY'], "${{ vars.CI_RUNNER_PROVIDER_HEAVY || 'blacksmith' }}")
        self.assertEqual(runner_env['CI_RUNNER_PROVIDER_LIGHT'], "${{ vars.CI_RUNNER_PROVIDER_LIGHT || 'github' }}")

        image_runner = load('build-image.yml')['jobs']['build']['runs-on']
        deploy_runner = load('deploy.yml')['jobs']['deploy']['runs-on']
        self.assertEqual(image_runner, ['self-hosted', 'nubit-x64'])
        self.assertIn('CI_RUNNER_PROVIDER_LIGHT', deploy_runner)

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

    def test_ci_composer_cache_uses_node_24_compatible_action_runtime(self):
        with (ROOT / '.github' / 'actions' / 'ci-stack' / 'action.yml').open(encoding='utf-8') as handle:
            action = yaml.safe_load(handle)

        cache = next(step for step in action['runs']['steps'] if step.get('name') == 'Restore Composer vendor cache')
        self.assertEqual(cache['uses'], 'actions/cache@v5')

        image = load('build-image.yml')
        actions = {step.get('name'): step.get('uses') for step in image['jobs']['build']['steps']}
        self.assertEqual(actions['Login to GHCR'], 'docker/login-action@v4')
        self.assertEqual(actions['Docker metadata'], 'docker/metadata-action@v6')

    def test_backend_ci_recreates_its_disposable_test_database_before_migrating(self):
        ci = load('ci-main.yml')
        step = next(
            step for step in ci['jobs']['backend']['steps']
            if step.get('name') == 'Create and migrate test database'
        )
        drop = step['run'].index('DROP DATABASE IF EXISTS demo_test')
        create = step['run'].index('CREATE DATABASE demo_test')
        migrate = step['run'].index('doctrine:migrations:migrate')
        self.assertLess(drop, create)
        self.assertLess(create, migrate)


if __name__ == '__main__':
    unittest.main()
