# Usage (une fois) : créer le dépôt GitHub public et pousser main
# Prérequis : GitHub CLI — https://cli.github.com/
#   winget install --id GitHub.cli
# Puis : gh auth login
#
# Exemple :
#   .\scripts\setup-github-repo.ps1 -Owner rdahani -Repo taskflow

param(
    [string] $Owner = "rdahani",
    [string] $Repo = "taskflow"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
    Write-Host "Installez GitHub CLI : winget install --id GitHub.cli"
    Write-Host "Puis : gh auth login"
    exit 1
}

gh auth status 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Exécutez : gh auth login (connexion navigateur, une fois)"
    exit 1
}

gh repo create "$Owner/$Repo" --public --source . --remote origin --push
if ($LASTEXITCODE -ne 0) {
    Write-Host "Si le dépôt existe déjà :"
    Write-Host "  git remote remove origin  # si besoin"
    Write-Host "  git remote add origin https://github.com/$Owner/$Repo.git"
    Write-Host "  git push -u origin main"
    exit 1
}

Write-Host "OK — pensez à configurer les secrets GitHub Actions (FTP + CONFIG_LOCAL_PHP). Voir DEPLOY.md"
