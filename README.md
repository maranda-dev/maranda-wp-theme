# maranda — carnet personnel

Thème WordPress en cours de préparation pour maranda.dev. Fond blanc, Arial, blog, parcours professionnel, carte interactive, dossiers Vigie Réseau et formulaire de contact.

## Installation
Installer le ZIP de la release : il contient le dossier `bsir-wordpress`, identifiant conservé pour les mises à jour de l’installation existante. Prévisualiser avant activation. Nécessite les contenus existants du site; ils restent dans la base WordPress et ne sont pas exportés ici.

## Contact
Le destinataire est l’adresse d’administration configurée dans WordPress. Elle n’est pas exposée dans la page publique. Le formulaire comprend une signature, un champ piège et une limitation des envois.

## Vigies
Le type mm_vigie et ses taxonomies sont exposés dans l’administration et l’API WordPress. Les champs rr_vigie_* permettent de renseigner catégorie, infrastructure, territoire, période, statut, vérification et crédit image. Les nouvelles fiches alimentent automatiquement la grille.

## Carte
Les observations publiques proviennent de la passerelle bsir/v1/public-map vers InspeKT; les données ne sont pas copiées dans le dépôt.

## État
Version de travail. Aucune activation du thème public n’a été effectuée. La photographie du bandeau Vigie est incluse dans le thème.


## Releases GitHub
La version est définie dans `style.css` et `MARANDA_THEME_VERSION` dans `functions.php`.
Après vérification et push, pousser un tag `vVERSION`. Le workflow vérifie la syntaxe PHP,
les tests du mécanisme de mise à jour et la concordance du tag, puis publie
`maranda-wp-theme-VERSION.zip` et `SHA256SUMS`. Les commits sur main sont vérifiés
sans être proposés comme mise à jour WordPress.

WordPress reçoit les releases stables depuis ce dépôt public, sans jeton GitHub.
Dans **Apparence → Mises à jour maranda**, vérifier la version et choisir les mises
à jour automatiques. Elles restent désactivées tant qu’elles n’ont pas été choisies.
Les versions brouillon et prerelease ne sont jamais installées par ce mécanisme.
Le dossier technique `bsir-wordpress` reste identique à celui du thème précédent afin de conserver les réglages et le chemin des mises à jour. La mise en ligne initiale est effectuée depuis le brouillon validé.

Pour les ajouts et corrections issus des veilles, voir [le fonctionnement des vigies](docs/vigies.md).


## Annonce temporaire
Le pop-up est repris de l’ancien thème, avec les mêmes champs `mm_announcement_*`,
le même message enregistré dans WordPress et la même apparence. Il s’affiche sur
l’accueil, se ferme avec les boutons ou Échap, et reste fermé pendant la session.
Un titre ou message modifié lui donne une nouvelle révision et le fait réapparaître.
Les champs restent modifiables dans **Apparence → Personnaliser → Annonce temporaire**.
Les valeurs du thème précédent servent de valeur de reprise tant que le nouveau thème
n’a pas de réglage propre. Aucun message personnel supplémentaire n’est exporté depuis
la base WordPress vers le dépôt.
