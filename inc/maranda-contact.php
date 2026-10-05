<?php
defined('ABSPATH') || exit;

/* Render only server-side: the recipient address never enters the page or JavaScript. */
add_shortcode('maranda_contact', static function () {
    $started = time();
    $endpoint = rest_url('maranda/v1/contact');
    if (isset($_GET['wpvibe_preview']) && is_string($_GET['wpvibe_preview'])) {
        $endpoint = add_query_arg('wpvibe_preview', sanitize_text_field(wp_unslash($_GET['wpvibe_preview'])), $endpoint);
    }
    wp_enqueue_script('maranda-contact', get_theme_file_uri('assets/maranda-contact.js'), [], '0.1.2', true);
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url($endpoint); ?>" class="mm-contact-form" data-maranda-contact data-endpoint="<?php echo esc_url($endpoint); ?>">
        <p class="mm-contact-help">Tous les champs sont obligatoires.</p>
        <label for="mm-contact-name">Ton nom</label>
        <input id="mm-contact-name" name="name" autocomplete="name" maxlength="120" required>
        <label for="mm-contact-email">Ton adresse courriel</label>
        <input id="mm-contact-email" name="email" type="email" autocomplete="email" maxlength="254" required>
        <label for="mm-contact-message">Ton message</label>
        <textarea id="mm-contact-message" name="message" minlength="10" maxlength="5000" required></textarea>
        <div class="mm-contact-hp" aria-hidden="true"><label for="mm-contact-website">Laisser vide</label>
        <input id="mm-contact-website" name="website" tabindex="-1" autocomplete="off"></div>
        <input type="hidden" name="started" value="<?php echo esc_attr($started); ?>">
        <input type="hidden" name="signature" value="<?php echo esc_attr(wp_hash('maranda-contact|' . $started)); ?>">
        <p class="mm-contact-help">Ces informations servent à te répondre. <a href="<?php echo esc_url(home_url('/confidentialite/')); ?>">Confidentialité</a>.</p>
        <noscript><p>Active JavaScript pour utiliser ce formulaire.</p></noscript><button type="submit" disabled>Envoyer mon message</button>
        <p class="mm-contact-status" role="status" aria-live="polite"></p>
    </form>
    <?php
    return preg_replace('/>\s+</', '><', trim(ob_get_clean()));
});

add_action('rest_api_init', static function () {
    register_rest_route('maranda/v1', '/contact', [
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'callback' => 'maranda_receive_contact',
    ]);
});

function maranda_receive_contact(WP_REST_Request $request) {
    if (strlen($request->get_body()) > 20000) return new WP_Error('contact_invalid', 'Le message est trop long.', ['status' => 400]);
    $data = $request->get_json_params();
    if (!is_array($data)) return new WP_Error('contact_invalid', 'Le formulaire est incomplet.', ['status' => 400]);
    foreach (['name', 'email', 'message', 'website', 'started', 'signature'] as $field) {
        if (isset($data[$field]) && !is_scalar($data[$field])) return new WP_Error('contact_invalid', 'Le formulaire est invalide.', ['status' => 400]);
    }
    $started = absint($data['started'] ?? 0);
    $signature = (string) ($data['signature'] ?? '');
    $age = time() - $started;
    if (!empty($data['website']) || !$started || $age < 3 || $age > 7200 || !hash_equals(wp_hash('maranda-contact|' . $started), $signature)) {
        return new WP_Error('contact_spam', 'Veuillez patienter quelques secondes. Si la page est ouverte depuis longtemps, rechargez-la.', ['status' => 400]);
    }
    $name = sanitize_text_field((string) ($data['name'] ?? ''));
    $raw_email = trim((string) ($data['email'] ?? ''));
    $email = sanitize_email($raw_email);
    $message = sanitize_textarea_field((string) ($data['message'] ?? ''));
    if (!$name || mb_strlen($name) > 120 || !$email || $email !== $raw_email || !is_email($email) || strlen($email) > 254 || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
        return new WP_Error('contact_invalid', 'Veuillez vérifier votre nom, votre courriel et votre message (10 à 5000 caractères).', ['status' => 400]);
    }
    $fingerprint = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('nonce'));
    $minute = 'mm_contact_min_' . $fingerprint;
    $day = 'mm_contact_day_' . $fingerprint;
    $window = get_transient($day);
    $count = is_array($window) ? (int) ($window['count'] ?? 0) : 0;
    if (get_transient($minute) || $count >= 6) return new WP_Error('contact_rate', 'Trop de messages ont été envoyés. Veuillez réessayer plus tard.', ['status' => 429]);
    // Reserve the interval before trying delivery, including when the mail transport fails.
    set_transient($minute, true, MINUTE_IN_SECONDS);
    $expires = is_array($window) ? (int) ($window['expires'] ?? 0) : time() + DAY_IN_SECONDS;
    set_transient($day, ['count' => $count + 1, 'expires' => $expires], max(1, $expires - time()));
    $body = "Message envoyé depuis maranda.dev\n\nNom : " . $name . "\nCourriel : " . $email . "\n\n" . $message;
    $sent = wp_mail(get_option('admin_email'), 'Nouveau message — maranda.dev', $body, ['Reply-To: ' . $email]);
    if (!$sent) return new WP_Error('contact_failed', 'Le message n’a pas pu être envoyé. Veuillez réessayer plus tard.', ['status' => 503]);
    return new WP_REST_Response(['message' => 'Merci, ton message a été transmis.'], 201);
}

add_action('template_redirect', static function () {
    if (!is_page('contact')) return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
    do_action('litespeed_control_set_nocache', 'Contact form freshness');
});
