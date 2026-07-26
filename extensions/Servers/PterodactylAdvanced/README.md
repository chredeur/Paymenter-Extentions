# Pterodactyl Advanced

Extension serveur Paymenter qui reprend l'extension Pterodactyl officielle et y ajoute
trois choses qu'elle ne couvre pas :

- **Montages** sélectionnés par produit et attachés à la création du serveur. Permet de
  monter un `.jar` protégé — Velocity, plugin propriétaire, configuration partagée — sur
  chaque serveur provisionné, sans intervention manuelle et sans que le client puisse le
  retirer depuis le gestionnaire de fichiers ou le SFTP.
- **Connexion automatique** : le bouton « Go to server » ouvre directement la session du
  client sur le panel, sans mot de passe.
- **Comptes gérés**, en option : le compte Pterodactyl est créé avec un mot de passe
  jamais communiqué, le client n'a donc rien à gérer.

Type : `Server`. Testé avec Paymenter 1.5.7 et Pterodactyl 1.14.1.

## Dépendance obligatoire

Pterodactyl n'expose aucun endpoint d'API applicative pour attacher un montage ni pour
ouvrir une session. Cette extension s'appuie sur l'addon :

**[chredeur/pterodactyl-api-addon](https://github.com/chredeur/pterodactyl-api-addon)**

Il doit être installé sur le panel avant d'utiliser cette extension. Sans lui, la
création de serveur fonctionne toujours mais l'attache échoue et l'erreur part dans les
logs Paymenter.

## Installation

Copier le dossier dans l'installation Paymenter :

```bash
cp -r extensions/Servers/PterodactylAdvanced /var/www/paymenter/extensions/Servers/
cd /var/www/paymenter && composer dump-autoload
```

Ou via l'administration Paymenter, `Extensions` puis l'envoi d'une archive ZIP du
dossier `PterodactylAdvanced`. Sur une installation Docker, l'import par l'interface
échoue tant que le correctif décrit dans le [README du dépôt](../../../README.md) n'est
pas appliqué.

L'extension n'apparaît pas dans la liste des extensions installées, c'est normal pour une
extension de type serveur. Elle se trouve dans `Admin > Servers` sous le nom
**Pterodactyl Advanced**, au moment de créer une entrée.

## Configuration

### 1. Le serveur

Dans `Admin > Servers`, créer une entrée avec l'extension `PterodactylAdvanced` :

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

## Connexion automatique

Le bouton **Go to server** de la page du service ouvre la session du client sur le panel
au lieu de le déposer sur une page de connexion. Rien à configurer, c'est actif dès que
l'addon du panel est installé.

Le jeton est demandé au panel au moment du clic, pas au rendu de la page : il ne peut pas
expirer pendant que le client lit sa page, et il n'apparaît jamais dans le HTML.

**Une session déjà ouverte sur le panel dans le même navigateur est fermée.** Le lien SSO
déconnecte puis reconnecte sur le compte visé — c'est voulu, il doit déposer le visiteur
sur le bon compte et non sur celui qui traînait. En pratique, si tu es connecté en
administrateur sur le panel et que tu testes le bouton, tu perds ta session admin. Teste
en fenêtre privée.

Le client est renvoyé vers la page de connexion normale du panel, sans message d'erreur,
dans ces cas :

- son compte a activé la double authentification — l'auto-connexion ne doit pas la
  contourner, et elle ne le peut pas techniquement ;
- son compte est administrateur ;
- l'addon n'est pas installé sur le panel, ou celui-ci est injoignable.

Le motif est journalisé en `error` avec le préfixe `[PterodactylAdvanced]`.

Si la personne qui déclenche le repli dispose d'un rôle d'administration, une notification
rouge persistante est également émise. Elle apparaît dans le panneau d'administration
Paymenter, au chargement de page suivant — Filament ne rend ses notifications que là, le
thème client ne les affiche pas, un client ne peut donc jamais la voir.

Rien n'est affiché quand l'échec survient hors requête, pendant un provisionnement en file
d'attente par exemple. Le journal reste la trace fiable :

```bash
grep PterodactylAdvanced storage/logs/laravel.log
```

## Mot de passe SFTP

La page du service affiche un panneau **SFTP** avec l'adresse au format
`sftp://hote:port`, le nom d'utilisateur, et un bouton de copie sur chaque valeur. Un
bouton **Générer un mot de passe SFTP** figure dans la barre d'actions.

Le mot de passe existant ne peut pas être affiché : le panel n'en conserve que l'empreinte.
Le bouton en génère donc un nouveau de 24 caractères, le pousse sur le compte via
`PATCH /api/application/users/{id}`, et l'affiche **une seule fois** au rechargement de la
page. Il n'est stocké nulle part.

Conséquence à annoncer au client : tout client SFTP configuré avec l'ancien mot de passe
cesse de fonctionner.

**Les comptes administrateurs sont refusés**, comme pour la connexion automatique. Rien ne
garantit que le compte panel derrière un service soit un compte client : un serveur créé à
la main, ou transféré ensuite, peut appartenir à un administrateur. Générer un mot de passe
reviendrait alors à afficher un mot de passe d'administrateur au client, soit une session
d'administration complète et non un accès aux fichiers. Le panneau remplace le bouton par
une explication, et la route répond `403` si la requête est fabriquée à la main. Le refus
est journalisé en `warning` et déclenche une notification côté administration.

Le nom d'utilisateur SFTP suit le format attendu par Pterodactyl, `{compte}.{identifiant}`.
Le port provient du node, il vaut 2022 par défaut.

L'affichage du panneau coûte trois appels à l'API du panel — serveur, node, utilisateur.
Si le panel est injoignable, le panneau l'indique et le reste de la page fonctionne
normalement.

Cette fonction est le complément naturel des comptes gérés : le client n'a pas de mot de
passe, et s'en génère un le jour où il a besoin du SFTP.

### Comptes gérés

Case **Managed accounts** dans la configuration du serveur, désactivée par défaut.

Une fois cochée, les comptes du panel sont créés avec un mot de passe aléatoire qui n'est
jamais communiqué. Le client n'a donc rien à gérer : il passe par Paymenter, clique sur
**Go to server**, et arrive dans son serveur.

Le mécanisme repose sur le comportement de `UserCreationService` : il ne génère un jeton
de réinitialisation, et n'envoie le lien « Setup Your Account », que si aucun mot de passe
n'est fourni. En fournir un transforme l'e-mail en simple notification de création.

Trois conséquences à connaître avant de cocher :

**Plus de SFTP par mot de passe.** Pterodactyl authentifie le SFTP par mot de passe du
panel ou par clé SSH. Sans mot de passe, le client doit enregistrer une clé SSH depuis son
compte — accessible via la connexion automatique. Si tes clients utilisent le SFTP,
préviens-les.

**Le client peut toujours s'en créer un.** La procédure « mot de passe oublié » du panel
reste ouverte et lui enverra un lien de réinitialisation. Ce n'est pas un verrou, c'est un
défaut de configuration : le compte n'a pas de mot de passe tant que personne n'en demande
un. Fermer cette porte demanderait de modifier le panel, et laisserait le client sans
aucun recours si Paymenter tombe.

**Paymenter devient le seul accès.** Si Paymenter est indisponible, tes clients n'ont plus
de chemin vers leurs serveurs, sauf à passer par la réinitialisation de mot de passe.

### Si les mots de passe ne partent pas

Sans la case cochée, le panel envoie un lien de création de mot de passe via la
notification `AccountCreated`. Elle implémente `ShouldQueue` : sans worker de file
d'attente actif sur le panel, l'e-mail n'est jamais envoyé, sans aucune erreur visible.
Vérifie `systemctl status pteroq` et la configuration `MAIL_*` du panel.

## Comportement

L'attache a lieu après la création du serveur, en un seul appel à
`POST /api/application/servers/{id}/mounts`.

- **Tout ou rien.** Si un seul montage de la liste est refusé, aucun n'est attaché.
- **Idempotent.** Un montage déjà présent n'est pas dupliqué.
- **Non bloquant.** Un échec d'attache n'annule pas la création du serveur : celui-ci
  existe déjà côté panel, échouer laisserait un serveur orphelin. L'erreur est écrite
  dans `storage/logs/laravel.log` avec le préfixe `[PterodactylAdvanced]`.

### Prise en compte par Wings

Docker n'applique un bind mount qu'à la création du conteneur. Sur un serveur fraîchement
provisionné, le conteneur n'existe pas encore : le montage est donc pris en compte au
premier démarrage, sans action supplémentaire.

Sur un serveur déjà en fonctionnement, un redémarrage est nécessaire.

## Diagnostic

Les erreurs sont journalisées avec le préfixe `[PterodactylAdvanced]`.

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

## Traductions

Les textes visibles par le client passent par le namespace `pterodactyladvanced`,
enregistré au `boot()` de l'extension. Anglais et français sont fournis.

Pour ajouter une langue, copier `resources/lang/en/messages.php` vers
`resources/lang/{code}/messages.php` et traduire les valeurs. Paymenter retombe sur
l'anglais pour toute clé manquante.

## Notes d'implémentation

L'extension **hérite** de `Paymenter\Extensions\Servers\Pterodactyl\Pterodactyl` au lieu
d'en dupliquer le code. Elle ne redéfinit que `getProductConfig()` et `createServer()`,
et réutilise la méthode `request()` du parent pour l'authentification et le transport
HTTP.

Conséquence à connaître lors d'une mise à jour de Paymenter : si l'extension officielle
change la signature de `createServer()` ou la forme de sa valeur de retour — attendue ici
sous la forme `['server' => <id>]` — c'est `createServer()` qu'il faut adapter.
