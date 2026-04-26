#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="gest-web-rent"
BUILD_DIR="build"
ZIP_NAME="${PLUGIN_SLUG}.zip"

rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

if command -v rsync >/dev/null 2>&1; then
  rsync -av \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.gitattributes' \
    --exclude='.gitignore' \
    --exclude='.gitkeep' \
    --exclude='build' \
    --exclude='scripts' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='.DS_Store' \
    --exclude='*.zip' \
    --exclude='*.log' \
    --exclude='*.tmp' \
    ./ "${BUILD_DIR}/${PLUGIN_SLUG}/"
else
  shopt -s dotglob nullglob
  for item in ./*; do
    base="$(basename "${item}")"
    case "${base}" in
      .git|.github|.gitattributes|.gitignore|.gitkeep|build|scripts|node_modules|vendor|.env|.env.*|.DS_Store|*.zip|*.log|*.tmp)
        continue
        ;;
    esac
    cp -R "${item}" "${BUILD_DIR}/${PLUGIN_SLUG}/"
  done
  find "${BUILD_DIR}/${PLUGIN_SLUG}" \( -name '.DS_Store' -o -name '*.zip' -o -name '*.log' -o -name '*.tmp' -o -name '.gitkeep' \) -delete
fi

test -f "${BUILD_DIR}/${PLUGIN_SLUG}/gest-web-rent.php"
grep -q "Plugin Name: Gest Web Rent" "${BUILD_DIR}/${PLUGIN_SLUG}/gest-web-rent.php"

cd "${BUILD_DIR}"

create_zip_with_powershell() {
  local shell_bin="$1"
  local ps_command='
$pluginSlug = "gest-web-rent"
$zipName = "gest-web-rent.zip"
if (Test-Path -LiteralPath $zipName) {
  Remove-Item -LiteralPath $zipName -Force
}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$base = (Get-Location).Path
$root = (Resolve-Path -LiteralPath $pluginSlug).Path
$zipPath = Join-Path $base $zipName
$archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
  [void] $archive.CreateEntry($pluginSlug + "/")
  Get-ChildItem -LiteralPath $root -Recurse -Directory | ForEach-Object {
    $entryName = $_.FullName.Substring($base.Length + 1).Replace("\", "/") + "/"
    [void] $archive.CreateEntry($entryName)
  }
  Get-ChildItem -LiteralPath $root -Recurse -File | ForEach-Object {
    $entryName = $_.FullName.Substring($base.Length + 1).Replace("\", "/")
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
  }
} finally {
  $archive.Dispose()
}
'
  "${shell_bin}" -NoProfile -Command "${ps_command}" >/dev/null
}

if command -v zip >/dev/null 2>&1; then
  zip -r "${ZIP_NAME}" "${PLUGIN_SLUG}"
elif command -v powershell.exe >/dev/null 2>&1; then
  create_zip_with_powershell powershell.exe
elif command -v pwsh >/dev/null 2>&1; then
  create_zip_with_powershell pwsh
else
  echo "Errore: serve zip oppure PowerShell Compress-Archive." >&2
  exit 1
fi

if command -v unzip >/dev/null 2>&1; then
  ZIP_LIST="$(unzip -l "${ZIP_NAME}")"
elif command -v powershell.exe >/dev/null 2>&1; then
  ZIP_LIST="$(powershell.exe -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::OpenRead((Resolve-Path '${ZIP_NAME}')).Entries | ForEach-Object { \$_.FullName }")"
elif command -v pwsh >/dev/null 2>&1; then
  ZIP_LIST="$(pwsh -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::OpenRead((Resolve-Path '${ZIP_NAME}')).Entries | ForEach-Object { \$_.FullName }")"
else
  echo "Errore: serve unzip oppure PowerShell per validare lo ZIP." >&2
  exit 1
fi

printf '%s\n' "${ZIP_LIST}"
printf '%s\n' "${ZIP_LIST}" | grep "${PLUGIN_SLUG}/gest-web-rent.php"
! printf '%s\n' "${ZIP_LIST}" | grep -E "${PLUGIN_SLUG}-main|frattomella-${PLUGIN_SLUG}|${PLUGIN_SLUG}-[0-9]"

echo "ZIP creato: ${BUILD_DIR}/${ZIP_NAME}"
