<?php
/** Restore the predecessor's notebook layout using the current WordPress article. */
defined('ABSPATH') || exit;
function maranda_blog_author_bio_html(int $user_id): string {
    $bio = trim((string) get_user_meta($user_id, 'description', true));
    $tags = ['p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'a' => ['href' => true, 'title' => true]];
    return $bio === '' ? '' : wpautop(wp_kses($bio, $tags));
}
add_shortcode('maranda_blog_article', static function (): string {
    if (!is_singular('post')) return '';
    ob_start();
?>
<div class="rr-blog">
<?php
    $locked = post_password_required();
    $plain = wp_strip_all_tags(strip_shortcodes(get_the_content(null, false)));
    $words = preg_match_all('/[\p{L}\p{N}]+(?:[’\x27-][\p{L}\p{N}]+)*/u', $plain);
    $minutes = max(1, (int) ceil($words / 220));
    $updated = get_the_modified_time('U') - get_the_time('U') > DAY_IN_SECONDS;
    $posts_page = (int) get_option('page_for_posts');
    $journal_url = $posts_page ? get_permalink($posts_page) : false;
?>
<article <?php post_class('rr-blog__article'); ?>>
    <header class="rr-blog__masthead">
        <div class="rr-blog__eyebrow"><span>Le carnet de <?php the_author(); ?></span><span>Idées · terrain · opinions</span></div>
        <div class="rr-blog__heading">
            <div>
                <div class="rr-blog__categories"><?php the_category(' / '); ?></div>
                <h1><?php the_title(); ?></h1>
                <?php if (!$locked && has_excerpt()) : ?><p class="rr-blog__intro"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
            </div>
            <div class="rr-blog__stamp" aria-hidden="true"><span>Carnet</span><strong>Hors<br>des lignes.</strong><span>Un regard personnel ↗</span></div>
        </div>
        <div class="rr-blog__meta">
            <span>Par <strong><?php the_author(); ?></strong></span>
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time>
            <?php if (!$locked) : ?><span><?php echo esc_html($minutes); ?> min de lecture</span><?php endif; ?>
            <?php if ($updated) : ?><span>Mis à jour le <?php echo esc_html(get_the_modified_date('j F Y')); ?></span><?php endif; ?>
        </div>
    </header>
    <?php if (!$locked && has_post_thumbnail()) : ?>
    <figure class="rr-blog__cover">
        <?php the_post_thumbnail('full', ['loading' => 'eager', 'fetchpriority' => 'high']); ?>
        <?php $caption = get_the_post_thumbnail_caption(); if ($caption) : ?><figcaption><?php echo wp_kses_post($caption); ?></figcaption><?php endif; ?>
    </figure>
    <?php endif; ?>
    <div class="rr-blog__reading">
        <aside class="rr-blog__rail" aria-label="Autour du billet">
            <span class="rr-blog__rail-mark" aria-hidden="true">↗</span>
            <p>Un billet.<br>Un point de vue.<br>La discussion est ouverte.</p>
            <a href="<?php echo esc_url(add_query_arg('url', get_permalink(), 'https://www.linkedin.com/sharing/share-offsite/')); ?>" target="_blank" rel="noopener noreferrer">Partager sur LinkedIn <span aria-hidden="true">↗</span></a>
            <a href="<?php echo esc_url('mailto:?subject=' . rawurlencode(get_the_title()) . '&body=' . rawurlencode(get_permalink())); ?>">Envoyer par courriel <span aria-hidden="true">↗</span></a>
        </aside>
        <div class="rr-blog__column">
            <div class="entry-content rr-blog__body">
                <?php the_content(); wp_link_pages(['before' => '<nav class="rr-blog__pages" aria-label="Pages du billet">', 'after' => '</nav>']); ?>
            </div>
            <?php if (!$locked && has_tag()) : ?><div class="rr-blog__tags"><?php the_tags('', ''); ?></div><?php endif; ?>
            <footer class="rr-blog__signature">
                <?php echo get_avatar(get_the_author_meta('ID'), 80, '', get_the_author(), ['class' => 'rr-blog__avatar']); ?>
                <div><span>Derrière ces lignes</span><strong><?php the_author(); ?></strong>
                <?php $bio = maranda_blog_author_bio_html((int) get_the_author_meta('ID')); if ($bio !== '') : ?><div class="rr-blog__bio"><?php echo $bio; ?></div><?php endif; ?></div>
            </footer>
        </div>
    </div>
    <?php if (comments_open() || get_comments_number()) : ?>
    <div class="rr-blog__comments"><?php echo do_blocks('<!-- wp:comments --><!-- wp:comments-title /--><!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template --><!-- wp:comments-pagination --><!-- wp:comments-pagination-previous /--><!-- wp:comments-pagination-next /--><!-- /wp:comments-pagination --><!-- wp:post-comments-form /--><!-- /wp:comments -->'); ?></div>
    <?php endif; ?>
    <nav class="rr-blog__next" aria-label="Continuer la lecture">
        <div><span>Avant ce billet</span><?php previous_post_link('%link', '← %title'); ?></div>
        <div><span>Après ce billet</span><?php next_post_link('%link', '%title →'); ?></div>
    </nav>
    <?php if ($journal_url) : ?><p class="rr-blog__return"><a href="<?php echo esc_url($journal_url); ?>">← Tous les articles</a></p><?php endif; ?>
</article>
</div>
<?php return (string) ob_get_clean();
});
