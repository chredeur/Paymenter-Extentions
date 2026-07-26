# Paymenter Extensions

Extensions pour [Paymenter](https://paymenter.org).

## Extensions disponibles

| Extension | Type | Description | Version |
| --- | --- | --- | --- |
| [PterodactylAdvanced](extensions/Servers/PterodactylAdvanced) | Server | Extension Pterodactyl : montages automatiques, connexion automatique au panel et comptes gérés | 2.3.0 |

Chaque extension a son propre README avec sa configuration, ses dépendances et son
diagnostic.

## Arborescence

Le dépôt reproduit l'arborescence attendue par Paymenter, ce qui permet d'installer une
extension par simple copie :

```
extensions/
├── Servers/
│   └── PterodactylAdvanced/
│       ├── PterodactylAdvanced.php
│       ├── routes.php
│       └── README.md
├── Gateways/
└── Others/
```

Paymenter impose cette convention : `extensions/{Type}s/{Nom}/{Nom}.php`, avec le
namespace `Paymenter\Extensions\{Type}s\{Nom}` et une classe portant le nom du dossier.
Les trois types sont `Servers`, `Gateways` et `Others`.

## Installation

### Par copie

```bash
cp -r extensions/Servers/PterodactylAdvanced /var/www/paymenter/extensions/Servers/
cd /var/www/paymenter && composer dump-autoload
```

### Par archive

Dans l'administration Paymenter, `Extensions`, puis envoyer une archive ZIP du dossier de
l'extension. L'archive doit contenir le dossier lui-même à sa racine, pas son contenu en
vrac. Les archives sont également publiées sur la page
[Releases](https://github.com/chredeur/Paymenter-Extensions/releases).

Une extension de type `Server` ou `Gateway` n'apparaît pas dans la liste des extensions
installées après l'import : seules celles de type `Other` y figurent. Elle se retrouve
dans `Admin > Servers` ou `Admin > Gateways`, au moment de créer une entrée.

### Sur Docker : erreur « cannot be a directory »

L'import par l'interface échoue avec :

```
Failed to upload extension
rename(): The first argument to copy() function cannot be a directory
```

Avec la compose officielle, Paymenter décompresse dans `/app/storage/app/extensions`
(volume nommé `paymenter_app`) puis déplace vers `/app/extensions` (bind mount depuis
l'hôte). Le noyau refuse `rename()` entre deux points de montage distincts, même sur le
même disque, et le repli `copy()` de PHP ne gère pas les répertoires.

Vérifier :

```bash
docker compose exec paymenter grep ' /app' /proc/self/mountinfo
```

Si `/app/extensions` apparaît sur une ligne distincte de `/app`, c'est le cas. Corriger
en plaçant le dossier de décompression à l'intérieur du même montage :

```bash
docker compose exec paymenter sh -c '
  rm -rf /app/storage/app/extensions
  mkdir -p /app/extensions/.uploads
  ln -s /app/extensions/.uploads /app/storage/app/extensions
  chown -R nginx:nginx /app/extensions/.uploads
'
```

Ne pas chercher à régler ça en ajoutant un montage dans le `docker-compose.yml` : ce
serait un troisième point de montage, donc le même refus.

Le dossier `.uploads` commence par un point, il reste donc invisible du scanner
d'extensions, qui utilise `glob('*')`. Le lien symbolique vit dans le volume nommé : il
survit aux redémarrages, mais doit être recréé si ce volume est supprimé lors d'une mise
à jour d'image.

C'est une limitation du cœur de Paymenter — `UploadExtensionService::handle()` utilise
`rename()` là où `File::moveDirectory()` conviendrait — et non un problème propre à ces
extensions.

## Compatibilité

Développé et testé avec Paymenter **1.5.7**.

## Contribuer

Une nouvelle extension suit la même arborescence :

1. créer `extensions/{Type}s/{Nom}/{Nom}.php`, la classe étendant `App\Classes\Extension\Server`,
   `Gateway` ou `Extension` ;
2. y ajouter l'attribut `#[ExtensionMeta(...)]` avec le nom, la description, la version et
   l'auteur ;
3. écrire un `README.md` dans le dossier de l'extension ;
4. l'ajouter au tableau en tête de ce fichier.

## Licence

MIT. Voir [LICENSE](LICENSE).
