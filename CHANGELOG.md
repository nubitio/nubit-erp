# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **SLICE-CP-02** — Granular role system for the control-plane admin panel: three roles (`ROLE_CP_VIEWER` ⊂ `ROLE_ADMIN` ⊂ `ROLE_SUPER_ADMIN`) with security hierarchy, `#[IsGranted]` guards on all controllers, and Twig `is_granted()` template guards. Destructive tenant actions require `ROLE_SUPER_ADMIN`.
- **SLICE-CP-01** — Login UI for the admin panel: `form_login` replaces `http_basic`, standalone Bootstrap 5.3 login page, CSRF protection, remember-me (7 days), brute-force throttling (5 req/min), and functional logout with session invalidation.
- **SLICE-CP-03** — Admin user management UI (ROLE_SUPER_ADMIN only): full CRUD via `AdminUserController` + `AdminUserRepository` (DBAL), password strength/confirm validation, AJAX active-toggle, and audit log for all operations.
