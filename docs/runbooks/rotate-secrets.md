# Runbook: Rotate Secrets

> **WARNING:** See Q3 in [SLICE-005 open questions](../slices/SLICE-005.md#10-open-questions--risks) —
> verify with the ops team whether these secrets are live in production before
> executing any rotation step.

| Field       | Value                                      |
|-------------|--------------------------------------------|
| created_at  | 2026-03-02                                 |
| updated_at  | 2026-03-02                                 |
| author      | agt-devops / SLICE-005                     |

---

## When to Rotate

Rotate a secret immediately if **any** of the following conditions are met:

1. **Suspected or confirmed exposure** — the secret was committed to git (even
   briefly), shared in a chat or email, logged to stdout/stderr, or visible in
   an error message.
2. **Employee or contractor offboarding** — anyone who had access to `.env.local`
   or the CI secrets store leaves the organisation.
3. **Annual rotation policy** — all long-lived credentials should be rotated
   at least once per year as a precaution.

---

## Emergency Contacts

| Role                  | Contact              |
|-----------------------|----------------------|
| Platform lead         | [TODO: fill in]      |
| DevOps on-call        | [TODO: fill in]      |
| Billing API support   | [TODO: fill in]      |
| Wasabi S3 account     | [TODO: fill in]      |

---

## 1. APP_SECRET

`APP_SECRET` is the Symfony application secret used to sign JWT tokens. Rotating
it **invalidates all existing sessions** — every logged-in user will be logged out
and must re-authenticate.

### Steps

1. Generate a new 32-byte hex secret:

   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```

2. Copy the output (64-character hex string).

3. Update `.env.local` on **every server** (never commit the real value to git):

   ```bash
   # On each server — replace the value
   APP_SECRET=<new-64-char-hex-value>
   ```

4. **Inform users**: all active JWT tokens are immediately invalid after the
   restart. Post a maintenance notice if the rotation is planned.

5. Perform a rolling restart of FrankenPHP containers to pick up the new value:

   ```bash
   docker compose pull && docker compose up -d --force-recreate web
   ```

6. Smoke-test: attempt a login and verify a new token is issued successfully.

7. Verify that the old secret is no longer present in any running container:

   ```bash
   docker exec efact-web env | grep APP_SECRET
   ```

---

## 2. S3_ACCESS_KEY / S3_ACCESS_SECRET (Wasabi S3)

`S3_ACCESS_KEY` and `S3_ACCESS_SECRET` are used for file uploads to the Wasabi
S3-compatible object store bucket `efact.uploads`.

### Steps

1. Log in to the **Wasabi Console** → IAM → Sub-users / Access Keys.

2. Select the IAM user associated with the efact service account.

3. Create a **new key pair**. Copy both the Access Key ID and the Secret.

4. Update `.env.local` on all servers with the new values:

   ```bash
   S3_ACCESS_KEY=<new-access-key-id>
   S3_ACCESS_SECRET=<new-secret-access-key>
   ```

5. Perform a rolling restart to pick up the new credentials:

   ```bash
   docker compose up -d --force-recreate web
   ```

6. **Smoke test**: upload a small test file through the application and
   verify it appears in the S3 bucket.

7. Delete the **old key pair** from the Wasabi IAM console only after
   confirming the new key works correctly.

---

## 3. GITHUB_TOKEN (GitHub Personal Access Token)

`GITHUB_TOKEN` is a GitHub Personal Access Token used by the application or CI
pipeline for repository operations (e.g. triggering deployments, reading
private packages).

### Steps

1. Go to **GitHub** → Settings → Developer Settings →
   Personal Access Tokens → Fine-grained tokens (or Classic, depending on
   which type was originally created).

2. Note the **scopes/permissions** on the current token before revoking it.

3. Generate a **new token** with the same scopes and a description that
   identifies the purpose (e.g. `efact-deploy-2026`).

4. Update the token in **all locations where it is used**:

   - **GitHub Actions secrets**: Settings → Secrets and variables → Actions →
     update `GITHUB_TOKEN` (or whichever secret name is used).
   - **`.env.local`** on servers if the token is used by the application directly.

   ```bash
   GITHUB_TOKEN=<new-token>
   ```

5. Restart any services that loaded the token at startup:

   ```bash
   docker compose up -d --force-recreate web
   ```

6. Verify that CI pipelines and any in-app GitHub API calls succeed.

7. **Revoke the old token** from the GitHub PAT settings page.

---

## 4. BILL_API_TOKEN (Billing API)

`BILL_API_TOKEN` is the bearer token used to authenticate requests to the
external billing API (`BILL_API_BASE_URL=https://api.efact.app/api/v1/`).

### Steps

1. Log in to the **billing API provider dashboard** or contact their support
   (see Emergency Contacts above).

2. Navigate to API credentials / access tokens and generate a **new token**.

3. Update `.env.local` on all servers:

   ```bash
   BILL_API_TOKEN=<new-token>
   ```

4. Perform a rolling restart:

   ```bash
   docker compose up -d --force-recreate web
   ```

5. **Smoke test**: trigger a billing API request (e.g. a dry-run invoice
   submission) and verify a `2xx` response.

6. Once the new token is confirmed working, **revoke the old token** via the
   billing provider dashboard.

---

## Checklist (use after each rotation)

```
[ ] New secret generated and stored in .env.local on all servers
[ ] Old secret removed from all .env.local files
[ ] No real secrets in git history (run: git log -p | grep -i 'SECRET\|TOKEN\|PASSWORD')
[ ] Containers restarted with new credentials
[ ] Smoke test passed
[ ] Old credential revoked / deleted from the provider
[ ] Rotation logged in the ops incident/change log
[ ] Team notified (if rotation affects active sessions or CI)
```
