<?php
 defined('ABSPATH') || exit;
 if (!maranda_announcement_setting('mm_announcement_enabled', true)) return;
 $title = maranda_announcement_setting('mm_announcement_title', 'Quelques mots sur ma démarche');
 $message = maranda_announcement_setting('mm_announcement_message', maranda_announcement_default());
 if (trim($message) === '') return;
 $revision = md5($title . "\n" . $message);
?>
<dialog class="mm-personal-note" id="mm-personal-note" aria-labelledby="mm-personal-note-title" data-revision="<?php echo esc_attr($revision); ?>" data-preview="<?php echo is_customize_preview() ? '1' : '0'; ?>">
    <div class="mm-personal-note__top"><span>Annonce temporaire</span><button type="button" data-note-close aria-label="Fermer cette annonce">Fermer ×</button></div>
    <div class="mm-personal-note__body">
        <h2 id="mm-personal-note-title"><?php echo esc_html($title); ?></h2>
        <?php echo wpautop(esc_html($message)); ?>
        <button class="mm-personal-note__continue" type="button" data-note-close>Continuer vers le site</button>
    </div>
</dialog>
