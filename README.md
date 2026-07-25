# Paymenter Extensions

Extensions pour [Paymenter](https://paymenter.org).

## Extensions disponibles

| Extension | Type | Description | Version |
| --- | --- | --- | --- |
| [PterodactylMounts](extensions/Servers/PterodactylMounts) | Server | Extension Pterodactyl avec attache automatique de montages à la création du serveur | 1.0.0 |

Chaque extension a son propre README avec sa configuration, ses dépendances et son
diagnostic.

## Arborescence

Le dépôt reproduit l'arborescence attendue par Paymenter, ce qui permet d'installer une
extension par simple copie :

```
extensions/
├── Servers/
│   └── PterodactylMounts/
│       ├── PterodactylMounts.php
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
cp -r extensions/Servers/PterodactylMounts /var/www/paymenter/extensions/Servers/
cd /var/www/paymenter && composer dump-autoload
```

### Par archive

Dans l'administration Paymenter, `Extensions`, puis envoyer une archive ZIP du dossier de
l'extension. Les archives sont également publiées sur la page
[Releases](https://github.com/chredeur/Paymenter-Extentions/releases).

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
