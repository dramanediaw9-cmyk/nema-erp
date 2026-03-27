# Sauvegarde et Restauration - Nema ERP

## Scripts disponibles

- `scripts/backup-nema-erp.ps1`
- `scripts/restore-nema-erp.ps1`

## Sauvegarde

Exemple :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\backup-nema-erp.ps1
```

Le script sauvegarde :

- la base MySQL
- `storage/app`
- `storage/logs`
- `.env.example`
- un fichier manifeste JSON

## Restauration

Exemple :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\restore-nema-erp.ps1 -BackupPath .\backups\20260322-120000
```

Le script restaure :

- la base MySQL cible
- les fichiers `storage`

## Bonnes pratiques

- faire une sauvegarde avant chaque mise a jour
- garder plusieurs sauvegardes horodatees
- tester une restauration au moins une fois par mois
- stocker une copie hors du serveur applicatif si possible
