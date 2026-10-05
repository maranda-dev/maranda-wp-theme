# Référencement du carnet personnel

Le thème décrit Mario Maranda comme une personne et maranda.dev comme un site personnel. Il ne publie pas de schéma LocalBusiness, d’offre commerciale, d’adresse courriel ou d’adresse privée.

## Éditer les aperçus

Dans **Pages**, **Articles** ou **Vigie Réseau**, ouvrir le contenu puis le panneau **Référencement et partage** sous l’éditeur. Un titre et une description personnalisés prennent priorité sur les valeurs automatiques. Les champs vides reprennent les valeurs prévues pour les pages principales, ou un extrait du contenu pour les articles et dossiers. La case d’exclusion ajoute `noindex` et retire le contenu du sitemap.

**Apparence → Référencement** présente les titres et descriptions des pages principales avec leurs liens d’édition. La mise à jour du thème ne remplace pas les métadonnées personnalisées enregistrées dans WordPress.

## Indexation et transition

- Les pages Services, Mandats, Municipalités, Méthode, Mission, Surveillance préventive, Circulation et les pages Sample Page sont conservées mais marquées `noindex`. Elles ne sont plus proposées dans le sitemap.
- Les anciens types de contenu Solutions, Clients et Preuves professionnelles sont exclus de l’indexation et de leurs sitemaps. Les projets et dossiers Vigie restent indexables.
- `/home/` redirige en 301 vers `/`, `/resume/` et `/skills/` vers `/profil-professionnel/`, et `/releves/` vers `/carte-des-releves/`. Ces pages sont exclues du sitemap.
- `/sitemap.xml` redirige vers le sitemap natif `/wp-sitemap.xml`. Le fichier robots.txt de WordPress annonce déjà ce dernier. Les auteurs, dates, recherches, pièces jointes et aperçus ne sont pas indexables ; le sitemap des utilisateurs est désactivé.
- Les versions paginées du blog et des archives ont leur propre canonical. Les paramètres de prévisualisation et de suivi ne sont pas repris dans les URLs canoniques.

Les pages exclues restent accessibles : ne pas les bloquer dans robots.txt, pour que les moteurs puissent lire leur directive `noindex`. Leur retrait des résultats dépend de leur prochaine exploration.

## Partage et données structurées

Open Graph et les cartes de partage utilisent le titre, la description et l’image mise en avant, ou la capture actuelle du thème. Les schémas JSON-LD relient les pages à Person et WebSite. Les articles sont décrits comme BlogPosting et les fiches Vigie comme CreativeWork. Les pages À propos et Parcours sont des ProfilePage.

Si Yoast, Rank Math ou All in One SEO est activé plus tard, le thème lui laisse les titres, descriptions, canoniques et données structurées pour éviter les doublons. Les règles de transition et d’exclusion des anciens contenus restent actives.

## Vérification et suivi

`scripts/test-seo.php` vérifie l’identité personnelle, les canonical paginées, l’exclusion des anciens services et des aperçus, la conservation des autres filtres de sitemap, les métadonnées personnalisées et l’échappement du JSON-LD. Les vérifications PHP, dossiers Vigie et mises à jour GitHub restent exécutées avant une release.

Le sitemap peut être envoyé à Google Search Console si le propriétaire y a accès. La disponibilité de métadonnées ou d’un sitemap ne garantit ni leur reprise exacte dans les résultats ni un classement.

Références : [Google : descriptions](https://developers.google.com/search/docs/appearance/snippet), [Google : URLs canoniques](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls), [WordPress : robots](https://developer.wordpress.org/reference/hooks/wp_robots/), [WordPress : requêtes de sitemap](https://developer.wordpress.org/reference/hooks/wp_sitemaps_posts_query_args/).
