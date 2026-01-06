#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${ROOT_DIR}/config/qz"

mkdir -p "${OUT_DIR}"

if [ -f "${OUT_DIR}/qz_private.key" ] || [ -f "${OUT_DIR}/qz_cert.pem" ]; then
  echo "QZ cert/key already exist in ${OUT_DIR}."
  echo "Remove them first if you want to re-generate."
  exit 1
fi

echo "Generating QZ Tray private key (with passphrase)..."
openssl genrsa -aes256 -out "${OUT_DIR}/qz_private.key" 2048

echo "Generating CSR..."
openssl req -new -key "${OUT_DIR}/qz_private.key" -out "${OUT_DIR}/qz.csr"

echo "Generating self-signed certificate (10 years)..."
openssl x509 -req -days 3650 -in "${OUT_DIR}/qz.csr" -signkey "${OUT_DIR}/qz_private.key" -out "${OUT_DIR}/qz_cert.pem"

echo "Done."
echo "Cert: ${OUT_DIR}/qz_cert.pem"
echo "Key:  ${OUT_DIR}/qz_private.key"
