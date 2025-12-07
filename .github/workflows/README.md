# CI/CD Workflows Documentation

This directory contains GitHub Actions workflows that automate the development, testing, security, and deployment processes for the eFact application.

## 📋 Workflow Overview

### Core Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| **ci.yml** | PR to main, push to main | Run tests, linting, and quality checks (CI only, no releases) |
| **deploy.yml** | Tag push (v*.*.*), Manual | Build and deploy application (production driven by release tags) |
| **security.yml** | Push, PR, Schedule, Manual | Security scanning and vulnerability detection |
| **versioning.yml** | Push to release, Manual | Semantic-release: tags + GitHub Releases (no changelog commits) |

### Operational Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| **rollback.yml** | Manual | Rollback to previous version |
| **performance.yml** | Manual, Schedule | Load testing and performance audits |
| **auto-merge.yml** | PR events | Auto-merge approved PRs |
| **pr-automation.yml** | PR events | Automated PR labeling and checks |

## 🚀 CI/CD Pipeline Flow

```
┌─────────────────────────────────────────────────────────────┐
│                     Developer Workflow                       │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  1. Pull Request Created                                     │
│     ├── Preflight Checks (title, conflicts, file size)      │
│     ├── Backend Tests (PHPUnit, GrumPHP, PHPStan)          │
│     ├── Frontend Build (TypeScript, Vite, Linting)          │
│     ├── Security Scans (Dependencies, SAST, Secrets)        │
│     └── PR Automation (labels, size, linked issues)         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  2. Merge to Main/Release                                    │
│     ├── Run Full CI Suite                                    │
│     ├── Build Docker Image (multi-stage, optimized)         │
│     ├── Security Scan Image (Trivy)                          │
│     ├── Generate SBOM                                        │
│     └── Push to GitHub Container Registry                    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Deploy to Staging                                        │
│     ├── Plan Deployment                                      │
│     ├── Deploy to Portainer                                  │
│     ├── Health Checks                                        │
│     └── Smoke Tests                                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  4. Create Release Tag                                       │
│     ├── Semantic Release (version bump)                      │
│     ├── Generate Changelog                                   │
│     ├── Create GitHub Release                                │
│     └── Trigger Production Deployment                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  5. Deploy to Production                                     │
│     ├── Manual Approval Required                             │
│     ├── Deploy Tagged Version                                │
│     ├── Health Checks & Monitoring                           │
│     └── Rollback Available if Needed                         │
└─────────────────────────────────────────────────────────────┘
```

## 🔧 Workflow Details

### CI Workflow (`ci.yml`)

**Purpose:** Continuous Integration - validates code quality and functionality

**Key Features:**
- ✅ Preflight checks (PR title validation, merge conflicts, file size)
- ✅ PHP quality checks (PHPStan, PHP CS Fixer, PHPMD)
- ✅ Automated tests with code coverage
- ✅ Frontend build and type checking
- ✅ Test reporting and artifacts
- ✅ Parallel job execution for speed

**Optimization:**
- Uses caching for dependencies (Composer, Yarn)
- Parallel execution of backend and frontend jobs
- Smart path filtering to skip unnecessary runs

### Deploy Workflow (`deploy.yml`)

**Purpose:** Deploy from release artifacts

**Environments:**
- **Production**: Triggered by tags `v*.*.*` (created on `release`) or manual dispatch with a version/image.
- **Staging**: Trigger via `workflow_dispatch` (provide environment + version/image).

**Key Features:**
- 🎯 Tag-driven production promotion
- 🐳 Multi-stage Docker builds with BuildKit cache
- 🔒 Container security scanning
- 📦 SBOM generation for compliance
- 🏥 Health checks post-deployment
- 📊 Deployment summaries and notifications

### Security Workflow (`security.yml`)

**Purpose:** Comprehensive security scanning

**Scans Include:**
1. **Dependency Scanning**: Composer & npm audit
2. **SAST**: CodeQL for PHP and JavaScript
3. **Container Scanning**: Trivy for Docker images
4. **Secret Detection**: TruffleHog for leaked credentials

**Schedule:** Runs daily at 2 AM UTC + on every PR/push

### Versioning Workflow (`versioning.yml`)

**Purpose:** Automated semantic versioning and release management on `release` branch

**Features:**
- 📝 Conventional Commits analysis
- 🔢 Automatic version bumping (major/minor/patch)
- 🏷️ GitHub releases with notes
- 🚀 Creates tags `v*.*.*` used for production deploys

**Version Bump Rules:**
- `feat:` → Minor version
- `fix:` → Patch version
- `BREAKING CHANGE` → Major version

### Rollback Workflow (`rollback.yml`)

**Purpose:** Quick rollback to previous stable version

**Safety Features:**
- ✅ Image verification before rollback
- ✅ Environment protection
- ✅ Manual confirmation required
- 📝 Automatic incident issue creation
- 🏥 Post-rollback health checks

**Usage:**
```bash
# Trigger manually via GitHub UI
# Select environment and optionally specify version
# Provide reason for rollback (required)
```

### Performance Workflow (`performance.yml`)

**Purpose:** Load testing and performance monitoring

**Tests:**
1. **Load Testing**: K6-based concurrent user simulation
2. **Lighthouse Audit**: Frontend performance metrics

**Schedule:** Weekly on Monday at 3 AM UTC

## 🔐 Required Secrets

Configure these secrets in GitHub Settings → Secrets:

### Portainer Deployment
```
PORTAINER_URL              # Portainer instance URL
PORTAINER_API_TOKEN        # Portainer API access token
PORTAINER_STACK_NAME       # Stack name for deployment
PORTAINER_ENDPOINT_ID      # Portainer endpoint ID
```

### Application
```
APP_URL                    # Application URL for health checks
```

### Optional
```
CODECOV_TOKEN             # Codecov for coverage reports
SLACK_WEBHOOK_URL         # Slack notifications
```

## 🏷️ Branch Strategy

```
feature/*  → PR into main (CI only)
main       → Integration (tests/QA), no releases
release    → Release branch; semantic-release runs here, creates tags/releases used for prod deploys
```
release       → Staging deployment (auto)
  
v*.*.*        → Production deployment (manual approval)
```

## 📊 Quality Gates

PRs must pass all of the following before merge:

- ✅ Preflight checks
- ✅ Backend tests (minimum coverage threshold)
- ✅ Frontend build
- ✅ Security scans (no critical vulnerabilities)
- ✅ Code review approval
- ✅ Conventional commit format

## 🎯 Best Practices

### For Developers

1. **PR Titles**: Use Conventional Commits format
   ```
   feat(auth): add JWT token refresh
   fix(invoice): correct tax calculation
   docs(readme): update deployment instructions
   ```

2. **Small PRs**: Keep changes focused and reviewable
   - < 500 lines: ✅ Ideal
   - 500-1000 lines: ⚠️ Consider splitting
   - \> 1000 lines: ❌ Break down

3. **Link Issues**: Always reference related issues
   ```markdown
   Closes #123
   Fixes #456
   Relates to #789
   ```

4. **Tests**: Include tests for new features
   - Unit tests for business logic
   - Integration tests for API endpoints

### For DevOps

1. **Secrets Rotation**: Rotate API tokens quarterly
2. **Monitoring**: Review security scan results weekly
3. **Performance**: Check performance test results weekly
4. **Rollback Plan**: Test rollback procedure quarterly

## 🔄 Workflow Maintenance

### Adding New Checks

1. Add job to `ci.yml`
2. Update `ci-summary` job dependencies
3. Document in this README
4. Test on feature branch

### Modifying Deployment

1. Update `deploy.yml`
2. Test on staging first
3. Document changes
4. Update runbooks

## 📚 Additional Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Semantic Versioning](https://semver.org/)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)

## 🆘 Troubleshooting

### CI Failures

**Problem**: Tests failing on CI but passing locally
- **Solution**: Check PHP/Node versions match
- **Check**: Database migrations applied
- **Verify**: Environment variables set correctly

**Problem**: Docker build fails
- **Solution**: Clear BuildKit cache
- **Action**: Re-run workflow with clean cache

### Deployment Issues

**Problem**: Health checks failing after deployment
- **Solution**: Check application logs in Portainer
- **Action**: Use rollback workflow if critical

**Problem**: Image not found in registry
- **Solution**: Verify build-and-push job succeeded
- **Check**: GITHUB_TOKEN has packages:write permission

## 📞 Support

For workflow issues:
1. Check workflow logs in Actions tab
2. Review job summaries for details
3. Contact DevOps team
4. Create issue with `area: ci/cd` label
