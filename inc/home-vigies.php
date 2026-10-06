<?php
defined('ABSPATH') || exit;
function maranda_home_vigies(): string {
    $query = new WP_Query([
        'post_type' => 'mm_vigie',
        'post_status' => 'publish',
        'posts_per_page' => 4,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
    ]);
    if (!$query->have_posts()) return '';

    $slides = '';
    $index = 0;
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $data = maranda_original_vigie_card_data($post_id);
        $image = $data['image']['url'] ?: get_theme_file_uri('assets/astro/route-quebec-hero.CETkoF_9_Z15aTvx.webp');
        $hidden = $index === 0 ? '' : ' hidden';
        $current = $index === 0 ? ' is-current' : '';
        $slides .= '<article class="rr-vigie-slide' . $current . '" data-vigie-slide' . $hidden . '>';
        $slides .= '<div class="rr-vigie-slide__visual"><img src="' . esc_url($image) . '" alt="' . esc_attr($data['image']['alt']) . '" loading="lazy"></div>';
        $card_timestamp = maranda_original_vigie_card_timestamp($post_id);
        $slides .= '<div class="rr-vigie-slide__date"><strong>' . esc_html(wp_date('d', $card_timestamp)) . '</strong><span>' . esc_html(wp_date('F', $card_timestamp)) . '</span></div>';
        $slides .= '<div class="rr-vigie-slide__card"><p class="rr-vigie-slide__nature">' . esc_html($data['nature']) . '</p><h3>' . esc_html(get_the_title()) . '</h3>';
        $slides .= '<p>' . esc_html($data['summary']) . '</p><p class="rr-vigie-slide__region">' . esc_html($data['region']) . '</p>';
        $slides .= '<a href="' . esc_url(get_permalink()) . '">Consulter la vigie <span aria-hidden="true">→</span></a></div></article>';
        $index++;
    }
    wp_reset_postdata();

    $count = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    return '<section class="rr-home-vigies" aria-labelledby="rr-home-vigies-title" data-home-vigies><header class="container"><div><p class="rr-kicker">Sur le réseau</p><h2 id="rr-home-vigies-title">Ma veille sur le réseau routier</h2><p>Des chantiers et des situations routières que je suis de près, par passion et par curiosité professionnelle.</p></div><a class="rr-prevost-simple" href="/vigie-reseau/">Toutes les vigies <span aria-hidden="true">›</span></a></header><div class="rr-home-vigies__stage container" aria-live="polite">' . $slides . '<div class="rr-home-vigies__controls"><span><b data-vigie-current>01</b> / ' . esc_html($count) . '</span><button type="button" data-vigie-prev aria-label="Vigie précédente"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 12H5m7-7-7 7 7 7"/></svg></button><button type="button" data-vigie-next aria-label="Vigie suivante"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14m-7-7 7 7-7 7"/></svg></button></div></div></section>';
}

add_shortcode('maranda_home_vigies', 'maranda_home_vigies');
