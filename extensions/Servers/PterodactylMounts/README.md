# Pterodactyl (Mounts)

Extension serveur Paymenter qui reprend l'extension Pterodactyl officielle et y ajoute
l'attache automatique de montages (mounts) à la création du serveur.

Cas d'usage : monter un `.jar` protégé — Velocity, plugin propriétaire, configuration
partagée — sur chaque serveur provisionné, sans intervention manuelle et sans que le
client puisse le retirer depuis le gestionnaire de fichiers ou le SFTP.

- Type : `Server`
- Testé avec : Paymenter 1.5.7, Pterodactyl 1.14.1

## Dépendance obligatoire

Pterodactyl n'expose aucun endpoint d'API applicative pour attacher un montage. Cette
extension s'appuie sur l'addon :

**[chredeur/pterodactyl-api-addon](https://github.com/chredeur/pterodactyl-api-addon)**

Il doit être installé sur le panel avant d'utiliser cette extension. Sans lui, la
création de serveur fonctionne toujours mais l'attache échoue et l'erreur part dans les
logs Paymenter.

## Installation

Copier le dossier dans l'installation Paymenter :

```bash
cp -r extensions/Servers/PterodactylMounts /var/www/paymenter/extensions/Servers/
cd /var/www/paymenter && composer dump-autoload
```

Ou via l'administration Paymenter, `Extensions` puis l'envoi d'une archive ZIP du
dossier `PterodactylMounts`. Sur une installation Docker, l'import par l'interface
échoue tant que le correctif décrit dans le [README du dépôt](../../../README.md) n'est
pas appliqué.

L'extension n'apparaît pas dans la liste des extensions installées, c'est normal pour une
extension de type serveur. Elle se trouve dans `Admin > Servers` sous le nom
**Pterodactyl (Mounts)**, au moment de créer une entrée.

## Configuration

### 1. Le serveur

Dans `Admin > Servers`, créer une entrée avec l'extension `PterodactylMounts` :

| Champ | Valeur |
| --- | --- |
| Pterodactyl URL | URL du panel, sans slash final |
| Pterodactyl API Key | Clé applicative `ptla_...` |

C'est une entrée distincte de celle de l'extension Pterodactyl officielle : l'URL et la
clé sont à saisir de nouveau. La clé doit appartenir à un compte administrateur et avoir
la permission d'écriture sur la ressource `servers`.

### 2. Le panel

Pour chaque montage à attacher :

1. le créer dans `/admin/mounts` ;
2. l'associer au **node** et à l'**egg** utilisés par le produit — l'attache est refusée
   sinon ;
3. ajouter son chemin source dans `allowed_mounts` du `config.yml` de Wings, puis
   redémarrer Wings.

L'identifiant numérique du montage se lit dans l'URL de sa page d'administration,
`/admin/mounts/view/3` donne l'ID `3`.

### 3. Le produit

Dans la configuration du produit, un champ **Mounts** a été ajouté en fin de formulaire.
C'est une liste déroulante à choix multiple, alimentée depuis le panel : elle ne propose
que les montages réellement attachables sur l'egg et le node sélectionnés, affichés sous
la forme `nom (cible)`. Choisir l'egg ou le node rafraîchit la liste.

Si le produit déploie par localisation plutôt que sur un node fixe, le filtrage ne porte
que sur l'egg. Un montage indisponible sur le node retenu au déploiement sera refusé à la
création, et l'erreur ira dans les logs.

Champ vide : aucun appel n'est fait, le comportement est identique à l'extension
Pterodactyl officielle.

Une liste vide ou un message d'erreur sous le champ signale que le panel n'a pas répondu.
La description affichée indique alors la cause exacte.

## Comportement

L'attache a lieu après la création du serveur, en un seul appel à
`POST /api/application/servers/{id}/mounts`.

- **Tout ou rien.** Si un seul montage de la liste est refusé, aucun n'est attaché.
- **Idempotent.** Un montage déjà présent n'est pas dupliqué.
- **Non bloquant.** Un échec d'attache n'annule pas la création du serveur : celui-ci
  existe déjà côté panel, échouer laisserait un serveur orphelin. L'erreur est écrite
  dans `storage/logs/laravel.log` avec le préfixe `[PterodactylMounts]`.

### Prise en compte par Wings

Docker n'applique un bind mount qu'à la création du conteneur. Sur un serveur fraîchement
provisionné, le conteneur n'existe pas encore : le montage est donc pris en compte au
premier démarrage, sans action supplémentaire.

Sur un serveur déjà en fonctionnement, un redémarrage est nécessaire.

## Diagnostic

Les erreurs sont journalisées avec le préfixe `[PterodactylMounts]`.

| Message | Cause |
| --- | --- |
| `The following mounts are not available to this server: ...` | Le montage n'est pas associé au node et à l'egg du serveur |
| `This account does not have permission to access the API.` | La clé n'appartient pas à un compte administrateur |
| `The requested resource could not be found on the server.` | ID de montage inexistant, ou addon absent du panel |

Vérifier l'état réel côté panel :

```bash
curl "https://panel.example.com/api/application/servers/12/mounts" \
  -H "Authorization: Bearer ptla_xxxxxxxxxxxxxxxxxxxx" \
  -H "Accept: application/json"
```

## Notes d'implémentation

L'extension **hérite** de `Paymenter\Extensions\Servers\Pterodactyl\Pterodactyl` au lieu
d'en dupliquer le code. Elle ne redéfinit que `getProductConfig()` et `createServer()`,
et réutilise la méthode `request()` du parent pour l'authentification et le transport
HTTP.

Conséquence à connaître lors d'une mise à jour de Paymenter : si l'extension officielle
change la signature de `createServer()` ou la forme de sa valeur de retour — attendue ici
sous la forme `['server' => <id>]` — c'est `createServer()` qu'il faut adapter.
