# Continuité des veilles routières

Les dossiers restent dans WordPress (`mm_vigie`). Une release GitHub ne crée,
ne remplace et ne supprime aucun dossier. Conserver leurs identifiants, URL,
images à la une, sources, dates et taxonomies existantes.

## Ajouter ou mettre à jour depuis une veille

Ce fonctionnement s'applique aux veilles « Urgences routières MTMD » et
« Vigie routière régionale », et aux dossiers municipaux ou autres veilles routières.

1. Vérifier les sources publiques, en privilégiant les documents officiels MTMD,
   Québec 511, SEAO et des municipalités. Distinguer date du fait et date de mise à jour.
2. Rechercher d'abord un dossier existant par projet, infrastructure et territoire.
   Ajouter le développement à ce dossier plutôt que créer un doublon.
3. Conserver le contenu antérieur utile. Ajouter une entrée datée de chronologie,
   les liens de sources, les faits confirmés et les inconnues encore à suivre.
4. Pour un nouveau dossier, préparer titre, extrait, contenu, taxonomies et image
   documentaire créditée. Ne pas présenter une observation rapportée comme une
   inspection officielle ni une demande budgétaire comme une dépense adoptée.
5. Enregistrer via WordPress ou son API authentifiée, suivant l'autorisation de
   publication donnée dans la conversation de veille. Vérifier le dossier et sa carte.

## Interface compatible

- Dossiers : `/wp-json/wp/v2/mm_vigie` (GET; POST authentifié pour ajout/modification).
- Taxonomies conservées : `mm_vigie_asset`, `mm_vigie_nature`,
  `mm_vigie_intervention`, `mm_vigie_contract`, `mm_vigie_region`.
- Présentation : métadonnées `rr_vigie_category`, `rr_vigie_infrastructure`,
  `rr_vigie_territory`, `rr_vigie_period`, `rr_vigie_date`, `rr_vigie_status`,
  `rr_vigie_verification`, `rr_vigie_image_url`, `rr_vigie_image_alt`,
  `rr_vigie_image_credit`.
- L'image à la une WordPress est prioritaire sur l'image renseignée en métadonnée.
- L'ajout d'un dossier publié l'affiche automatiquement dans la grille Vigies.

Le thème assure cette compatibilité. Il ne lance pas une veille autonome et ne
change pas les tâches ou conversations de surveillance existantes.
