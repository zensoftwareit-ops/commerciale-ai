<?php
/**
 * Plugin Name: Commerciale AI Forms
 * Description: Moduli commerciali, archivio richieste, notifiche e tracciamento conversioni per Commerciale AI.
 * Version: 1.0.0
 * Author: Zen Software
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: commerciale-ai-forms
 * License: MIT
 */

defined('ABSPATH') || exit;

final class Commerciale_AI_Forms
{
    private const VERSION = '1.0.0';
    private const POST_TYPE = 'cai_inquiry';
    private const FORM_TYPES = ['sales', 'contact', 'support'];

    public static function boot(): void
    {
        add_action('init', [self::class, 'register_post_type']);
        add_action('init', [self::class, 'register_shortcodes'], 5);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_init', [self::class, 'privacy_policy_content']);
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('add_meta_boxes_'.self::POST_TYPE, [self::class, 'add_meta_boxes']);
        add_action('save_post_'.self::POST_TYPE, [self::class, 'save_inquiry']);
        add_action('admin_post_cai_form_submit', [self::class, 'submit']);
        add_action('admin_post_nopriv_cai_form_submit', [self::class, 'submit']);
        add_filter('manage_'.self::POST_TYPE.'_posts_columns', [self::class, 'columns']);
        add_action('manage_'.self::POST_TYPE.'_posts_custom_column', [self::class, 'column_content'], 10, 2);
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'register_eraser']);
        add_action('cai_forms_cleanup', [self::class, 'cleanup']);
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), [self::class, 'settings_link']);
    }

    public static function activate(): void
    {
        self::register_post_type();
        if (! wp_next_scheduled('cai_forms_cleanup')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'cai_forms_cleanup');
        update_option('cai_forms_version', self::VERSION, false);
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('cai_forms_cleanup');
        flush_rewrite_rules(false);
    }

    public static function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Richieste', 'commerciale-ai-forms'),
                'singular_name' => __('Richiesta', 'commerciale-ai-forms'),
                'menu_name' => __('Richieste sito', 'commerciale-ai-forms'),
                'all_items' => __('Tutte le richieste', 'commerciale-ai-forms'),
                'edit_item' => __('Dettaglio richiesta', 'commerciale-ai-forms'),
                'search_items' => __('Cerca richieste', 'commerciale-ai-forms'),
                'not_found' => __('Nessuna richiesta trovata.', 'commerciale-ai-forms'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-email-alt2',
            'supports' => ['title'],
            'capabilities' => [
                'edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options',
                'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options',
                'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options',
                'edit_private_posts' => 'manage_options', 'edit_published_posts' => 'manage_options', 'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap' => false,
        ]);
    }

    public static function register_shortcodes(): void
    {
        add_shortcode('commerciale_ai_form', [self::class, 'form_shortcode']);
        add_shortcode('commerciale_ai_contact', [self::class, 'contact_shortcode']);
    }

    public static function register_assets(): void
    {
        wp_register_style('commerciale-ai-forms', plugins_url('assets/forms.css', __FILE__), [], self::VERSION);
        wp_register_script('commerciale-ai-forms', plugins_url('assets/forms.js', __FILE__), [], self::VERSION, true);
    }

    public static function contact_shortcode(): string
    {
        ob_start(); ?>
        <section class="contact-layout">
            <div class="contact-card contact-card--accent">
                <p class="content-kicker"><?php esc_html_e('Prima dell’acquisto', 'commerciale-ai-forms'); ?></p>
                <h2><?php esc_html_e('Tre informazioni ci aiutano a risponderti bene.', 'commerciale-ai-forms'); ?></h2>
                <ol><li><?php esc_html_e('Quante persone lavorano sui lead?', 'commerciale-ai-forms'); ?></li><li><?php esc_html_e('Quanti nuovi contatti ricevete in un mese?', 'commerciale-ai-forms'); ?></li><li><?php esc_html_e('Da quali siti o caselle arrivano?', 'commerciale-ai-forms'); ?></li></ol>
                <p class="contact-note"><?php esc_html_e('Per pagamenti e abbonamenti già attivi puoi usare l’Area cliente.', 'commerciale-ai-forms'); ?></p>
            </div>
            <div class="contact-card contact-card--form"><?php echo self::form_shortcode(['type' => 'sales']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </section>
        <?php return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $attributes */
    public static function form_shortcode(array $attributes = []): string
    {
        $attributes = shortcode_atts(['type' => 'contact', 'title' => 'Parliamo del tuo flusso commerciale'], $attributes, 'commerciale_ai_form');
        $type = sanitize_key((string) $attributes['type']);
        if (! in_array($type, self::FORM_TYPES, true)) $type = 'contact';
        $status = sanitize_key((string) ($_GET['cai_form'] ?? ''));
        $status_type = sanitize_key((string) ($_GET['cai_form_type'] ?? ''));
        $success = $status === 'success' && $status_type === $type;
        $error = $status === 'error' && $status_type === $type;
        $started = time();
        $token = hash_hmac('sha256', $type.'|'.$started, wp_salt('nonce'));
        $privacy_url = get_privacy_policy_url();
        wp_enqueue_style('commerciale-ai-forms');
        wp_enqueue_script('commerciale-ai-forms');

        ob_start(); ?>
        <div class="cai-form-wrap" id="commerciale-ai-form"<?php echo $success ? ' data-cai-form-success="1" data-cai-form-type="'.esc_attr($type).'" data-cai-ga4-event="'.esc_attr(self::ga4_event()).'"' : ''; ?>>
            <?php if ($success) : ?>
                <div class="cai-form-notice cai-form-notice--success" role="status"><strong><?php esc_html_e('Richiesta ricevuta.', 'commerciale-ai-forms'); ?></strong> <?php echo esc_html((string) get_option('cai_forms_success_message', 'Ti ricontatteremo appena possibile.')); ?></div>
            <?php elseif ($error) : ?>
                <div class="cai-form-notice cai-form-notice--error" role="alert"><strong><?php esc_html_e('Invio non riuscito.', 'commerciale-ai-forms'); ?></strong> <?php esc_html_e('Controlla i campi richiesti e riprova.', 'commerciale-ai-forms'); ?></div>
            <?php endif; ?>
            <h2><?php echo esc_html((string) $attributes['title']); ?></h2>
            <p class="cai-form-intro"><?php esc_html_e('Compila i campi essenziali. Non inviare password, chiavi API o dati personali dei tuoi clienti.', 'commerciale-ai-forms'); ?></p>
            <form class="cai-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="cai_form_submit">
                <input type="hidden" name="cai_form_type" value="<?php echo esc_attr($type); ?>">
                <input type="hidden" name="cai_started" value="<?php echo esc_attr((string) $started); ?>">
                <input type="hidden" name="cai_form_token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="cai_return" value="<?php echo esc_url(self::current_url()); ?>">
                <?php wp_nonce_field('cai_form_submit', 'cai_form_nonce'); ?>
                <p class="cai-form-trap" aria-hidden="true"><label>Website<input type="text" name="cai_website" value="" tabindex="-1" autocomplete="off"></label></p>
                <div class="cai-form-grid">
                    <?php echo self::field('cai_name', __('Nome e cognome', 'commerciale-ai-forms'), 'text', true, 'name'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo self::field('cai_email', __('Email di lavoro', 'commerciale-ai-forms'), 'email', true, 'email'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo self::field('cai_company', __('Azienda', 'commerciale-ai-forms'), 'text', false, 'organization'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo self::field('cai_phone', __('Telefono', 'commerciale-ai-forms'), 'tel', false, 'tel'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <label><span><?php esc_html_e('Persone che gestiscono i lead', 'commerciale-ai-forms'); ?></span><select name="cai_team_size"><option value=""><?php esc_html_e('Seleziona', 'commerciale-ai-forms'); ?></option><option value="1">1</option><option value="2-3">2–3</option><option value="4-10">4–10</option><option value="11+">11+</option></select></label>
                    <label><span><?php esc_html_e('Nuovi lead al mese', 'commerciale-ai-forms'); ?></span><select name="cai_monthly_leads"><option value=""><?php esc_html_e('Seleziona', 'commerciale-ai-forms'); ?></option><option value="0-50">0–50</option><option value="51-200">51–200</option><option value="201-1000">201–1.000</option><option value="1000+">Oltre 1.000</option></select></label>
                </div>
                <label><span><?php esc_html_e('Da dove arrivano oggi i lead?', 'commerciale-ai-forms'); ?></span><input type="text" name="cai_sources" placeholder="<?php esc_attr_e('Sito, email, campagne, inserimento manuale…', 'commerciale-ai-forms'); ?>"></label>
                <label><span><?php esc_html_e('Come possiamo aiutarti?', 'commerciale-ai-forms'); ?> *</span><textarea name="cai_message" rows="5" required maxlength="3000"></textarea></label>
                <label class="cai-form-consent"><input type="checkbox" name="cai_privacy" value="1" required><span><?php esc_html_e('Ho letto l’informativa privacy e acconsento al trattamento dei dati per ricevere risposta alla richiesta.', 'commerciale-ai-forms'); ?><?php if ($privacy_url) : ?> <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="privacy-policy noopener"><?php esc_html_e('Leggi l’informativa', 'commerciale-ai-forms'); ?></a><?php endif; ?></span></label>
                <button class="cai-form-submit" type="submit"><?php esc_html_e('Invia la richiesta', 'commerciale-ai-forms'); ?> <span aria-hidden="true">→</span></button>
            </form>
        </div>
        <?php return (string) ob_get_clean();
    }

    private static function field(string $name, string $label, string $type, bool $required, string $autocomplete): string
    {
        return '<label><span>'.esc_html($label).($required ? ' *' : '').'</span><input type="'.esc_attr($type).'" name="'.esc_attr($name).'" autocomplete="'.esc_attr($autocomplete).'"'.($required ? ' required' : '').' maxlength="190"></label>';
    }

    public static function submit(): void
    {
        $type = sanitize_key((string) ($_POST['cai_form_type'] ?? 'contact'));
        if (! in_array($type, self::FORM_TYPES, true)) $type = 'contact';
        $return = self::return_url($type);
        if (! isset($_POST['cai_form_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cai_form_nonce'])), 'cai_form_submit')) self::redirect($return, 'error', $type);

        $started = absint($_POST['cai_started'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_POST['cai_form_token'] ?? ''));
        $expected = hash_hmac('sha256', $type.'|'.$started, wp_salt('nonce'));
        if ($started <= 0 || time() - $started < 2 || time() - $started > 2 * HOUR_IN_SECONDS || ! hash_equals($expected, $token)) self::redirect($return, 'error', $type);
        if (trim((string) ($_POST['cai_website'] ?? '')) !== '') self::redirect($return, 'success', $type);

        $rate_key = 'cai_form_'.hash_hmac('sha256', self::request_fingerprint(), wp_salt('auth'));
        if (get_transient($rate_key)) self::redirect($return, 'error', $type);

        $data = [
            'type' => $type,
            'name' => sanitize_text_field(wp_unslash($_POST['cai_name'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['cai_email'] ?? '')),
            'company' => sanitize_text_field(wp_unslash($_POST['cai_company'] ?? '')),
            'phone' => sanitize_text_field(wp_unslash($_POST['cai_phone'] ?? '')),
            'team_size' => sanitize_text_field(wp_unslash($_POST['cai_team_size'] ?? '')),
            'monthly_leads' => sanitize_text_field(wp_unslash($_POST['cai_monthly_leads'] ?? '')),
            'sources' => sanitize_text_field(wp_unslash($_POST['cai_sources'] ?? '')),
            'message' => sanitize_textarea_field(wp_unslash($_POST['cai_message'] ?? '')),
            'privacy' => ! empty($_POST['cai_privacy']) ? '1' : '0',
        ];
        if ($data['name'] === '' || ! is_email($data['email']) || $data['message'] === '' || $data['privacy'] !== '1') self::redirect($return, 'error', $type);

        set_transient($rate_key, '1', MINUTE_IN_SECONDS);
        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => sprintf('%s · %s · %s', self::type_label($type), $data['name'], wp_date('d/m/Y H:i')),
        ], true);
        if (is_wp_error($post_id)) self::redirect($return, 'error', $type);

        foreach ($data as $key => $value) update_post_meta((int) $post_id, '_cai_'.$key, $value);
        update_post_meta((int) $post_id, '_cai_status', 'new');
        update_post_meta((int) $post_id, '_cai_origin', esc_url_raw(wp_get_referer() ?: $return));
        update_post_meta((int) $post_id, '_cai_privacy_at', current_time('mysql', true));
        $sent = self::notify($data, (int) $post_id);
        update_post_meta((int) $post_id, '_cai_notification_sent', $sent ? '1' : '0');
        self::redirect($return, 'success', $type);
    }

    /** @param array<string,string> $data */
    private static function notify(array $data, int $post_id): bool
    {
        $recipient = sanitize_email((string) get_option('cai_forms_recipient_email', get_option('admin_email')));
        if (! is_email($recipient)) return false;
        $subject = sprintf('[Commerciale AI] %s da %s', self::type_label($data['type']), $data['name']);
        $lines = [
            'Nuova richiesta dal sito Commerciale AI', '',
            'Tipo: '.self::type_label($data['type']), 'Nome: '.$data['name'], 'Email: '.$data['email'],
            'Azienda: '.($data['company'] ?: '—'), 'Telefono: '.($data['phone'] ?: '—'),
            'Persone sul lead: '.($data['team_size'] ?: '—'), 'Lead mensili: '.($data['monthly_leads'] ?: '—'),
            'Provenienza lead: '.($data['sources'] ?: '—'), '', 'Messaggio:', $data['message'], '',
            'Apri nel pannello: '.admin_url('post.php?post='.$post_id.'&action=edit'),
        ];
        return wp_mail($recipient, $subject, implode("\n", $lines), ['Reply-To: '.$data['name'].' <'.$data['email'].'>']);
    }

    public static function columns(array $columns): array
    {
        return ['cb' => $columns['cb'] ?? '', 'title' => __('Richiesta', 'commerciale-ai-forms'), 'cai_type' => __('Tipo', 'commerciale-ai-forms'), 'cai_contact' => __('Contatto', 'commerciale-ai-forms'), 'cai_company' => __('Azienda', 'commerciale-ai-forms'), 'cai_status' => __('Stato', 'commerciale-ai-forms'), 'date' => __('Data', 'commerciale-ai-forms')];
    }

    public static function column_content(string $column, int $post_id): void
    {
        if ($column === 'cai_type') echo esc_html(self::type_label((string) get_post_meta($post_id, '_cai_type', true)));
        if ($column === 'cai_contact') echo '<a href="mailto:'.esc_attr((string) get_post_meta($post_id, '_cai_email', true)).'">'.esc_html((string) get_post_meta($post_id, '_cai_email', true)).'</a>';
        if ($column === 'cai_company') echo esc_html((string) get_post_meta($post_id, '_cai_company', true) ?: '—');
        if ($column === 'cai_status') echo esc_html(self::status_label((string) get_post_meta($post_id, '_cai_status', true)));
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box('cai_inquiry_details', __('Dati della richiesta', 'commerciale-ai-forms'), [self::class, 'details_box'], self::POST_TYPE, 'normal', 'high');
    }

    public static function details_box(WP_Post $post): void
    {
        wp_nonce_field('cai_save_inquiry', 'cai_inquiry_nonce');
        $fields = ['name' => 'Nome', 'email' => 'Email', 'company' => 'Azienda', 'phone' => 'Telefono', 'team_size' => 'Persone sul lead', 'monthly_leads' => 'Lead mensili', 'sources' => 'Provenienza lead', 'message' => 'Messaggio', 'origin' => 'Pagina di origine', 'privacy_at' => 'Consenso privacy (UTC)'];
        echo '<table class="widefat striped"><tbody>';
        foreach ($fields as $key => $label) {
            $value = (string) get_post_meta($post->ID, '_cai_'.$key, true);
            echo '<tr><th style="width:190px">'.esc_html($label).'</th><td>'.($key === 'email' ? '<a href="mailto:'.esc_attr($value).'">'.esc_html($value).'</a>' : nl2br(esc_html($value ?: '—'))).'</td></tr>';
        }
        echo '</tbody></table><p><label for="cai_status"><strong>'.esc_html__('Stato', 'commerciale-ai-forms').'</strong></label> <select id="cai_status" name="cai_status">';
        $current = (string) get_post_meta($post->ID, '_cai_status', true);
        foreach (['new' => 'Nuova', 'working' => 'In lavorazione', 'closed' => 'Chiusa'] as $value => $label) echo '<option value="'.esc_attr($value).'" '.selected($current, $value, false).'>'.esc_html($label).'</option>';
        echo '</select></p>';
    }

    public static function save_inquiry(int $post_id): void
    {
        if (! isset($_POST['cai_inquiry_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cai_inquiry_nonce'])), 'cai_save_inquiry') || ! current_user_can('manage_options')) return;
        $status = sanitize_key((string) ($_POST['cai_status'] ?? 'new'));
        if (in_array($status, ['new', 'working', 'closed'], true)) update_post_meta($post_id, '_cai_status', $status);
    }

    public static function admin_menu(): void
    {
        add_submenu_page('edit.php?post_type='.self::POST_TYPE, __('Impostazioni moduli', 'commerciale-ai-forms'), __('Impostazioni', 'commerciale-ai-forms'), 'manage_options', 'cai-forms-settings', [self::class, 'settings_page']);
    }

    public static function register_settings(): void
    {
        register_setting('cai_forms_settings', 'cai_forms_recipient_email', ['type' => 'string', 'sanitize_callback' => 'sanitize_email']);
        register_setting('cai_forms_settings', 'cai_forms_success_message', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('cai_forms_settings', 'cai_forms_ga4_event', ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_event']]);
        register_setting('cai_forms_settings', 'cai_forms_retention_days', ['type' => 'integer', 'sanitize_callback' => static fn($value): int => min(3650, max(30, absint($value)))]);
    }

    public static function sanitize_event(mixed $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $value);
        return $value !== '' ? substr($value, 0, 40) : 'generate_lead';
    }

    public static function settings_page(): void
    {
        if (! current_user_can('manage_options')) return; ?>
        <div class="wrap"><h1><?php esc_html_e('Commerciale AI · Moduli', 'commerciale-ai-forms'); ?></h1><p><?php esc_html_e('Le richieste vengono salvate nel database e inviate anche all’indirizzo indicato. Per una consegna affidabile configura SMTP sul server.', 'commerciale-ai-forms'); ?></p>
        <form method="post" action="options.php"><?php settings_fields('cai_forms_settings'); ?><table class="form-table" role="presentation">
            <tr><th><label for="cai_forms_recipient_email"><?php esc_html_e('Email destinataria', 'commerciale-ai-forms'); ?></label></th><td><input class="regular-text" type="email" id="cai_forms_recipient_email" name="cai_forms_recipient_email" value="<?php echo esc_attr((string) get_option('cai_forms_recipient_email', get_option('admin_email'))); ?>"></td></tr>
            <tr><th><label for="cai_forms_success_message"><?php esc_html_e('Messaggio di conferma', 'commerciale-ai-forms'); ?></label></th><td><input class="regular-text" type="text" id="cai_forms_success_message" name="cai_forms_success_message" value="<?php echo esc_attr((string) get_option('cai_forms_success_message', 'Ti ricontatteremo appena possibile.')); ?>"></td></tr>
            <tr><th><label for="cai_forms_ga4_event"><?php esc_html_e('Evento GA4', 'commerciale-ai-forms'); ?></label></th><td><input class="regular-text" type="text" id="cai_forms_ga4_event" name="cai_forms_ga4_event" value="<?php echo esc_attr(self::ga4_event()); ?>"><p class="description"><?php esc_html_e('Evento consigliato: generate_lead. Viene emesso solo dopo un invio riuscito.', 'commerciale-ai-forms'); ?></p></td></tr>
            <tr><th><label for="cai_forms_retention_days"><?php esc_html_e('Conservazione richieste', 'commerciale-ai-forms'); ?></label></th><td><input class="small-text" type="number" min="30" max="3650" id="cai_forms_retention_days" name="cai_forms_retention_days" value="<?php echo esc_attr((string) get_option('cai_forms_retention_days', 730)); ?>"> <?php esc_html_e('giorni', 'commerciale-ai-forms'); ?><p class="description"><?php esc_html_e('Le richieste più vecchie vengono eliminate automaticamente.', 'commerciale-ai-forms'); ?></p></td></tr>
        </table><?php submit_button(); ?></form><hr><p><code>[commerciale_ai_contact]</code> <?php esc_html_e('modulo commerciale completo', 'commerciale-ai-forms'); ?><br><code>[commerciale_ai_form type="contact"]</code> <?php esc_html_e('modulo singolo riutilizzabile', 'commerciale-ai-forms'); ?></p></div>
        <?php
    }

    public static function settings_link(array $links): array
    {
        array_unshift($links, '<a href="'.esc_url(admin_url('edit.php?post_type='.self::POST_TYPE.'&page=cai-forms-settings')).'">'.esc_html__('Impostazioni', 'commerciale-ai-forms').'</a>');
        return $links;
    }

    public static function cleanup(): void
    {
        $days = min(3650, max(30, (int) get_option('cai_forms_retention_days', 730)));
        $ids = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'private', 'date_query' => [['before' => $days.' days ago']], 'fields' => 'ids', 'posts_per_page' => 100]);
        foreach ($ids as $id) wp_delete_post((int) $id, true);
    }

    public static function privacy_policy_content(): void
    {
        if (! function_exists('wp_add_privacy_policy_content')) return;
        wp_add_privacy_policy_content('Commerciale AI Forms', '<p>'.esc_html__('Quando invii un modulo, conserviamo i dati inseriti, la pagina di provenienza, la data del consenso e lo stato della richiesta per poter rispondere e gestire il rapporto commerciale. I dati vengono inviati anche all’indirizzo email configurato dall’amministratore e cancellati automaticamente secondo il periodo di conservazione impostato.', 'commerciale-ai-forms').'</p>');
    }

    public static function register_exporter(array $exporters): array
    {
        $exporters['commerciale-ai-forms'] = ['exporter_friendly_name' => __('Richieste Commerciale AI', 'commerciale-ai-forms'), 'callback' => [self::class, 'export_personal_data']];
        return $exporters;
    }

    public static function register_eraser(array $erasers): array
    {
        $erasers['commerciale-ai-forms'] = ['eraser_friendly_name' => __('Richieste Commerciale AI', 'commerciale-ai-forms'), 'callback' => [self::class, 'erase_personal_data']];
        return $erasers;
    }

    public static function export_personal_data(string $email, int $page = 1): array
    {
        $query = new WP_Query(['post_type' => self::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => 50, 'paged' => $page, 'meta_key' => '_cai_email', 'meta_value' => sanitize_email($email)]);
        $data = [];
        foreach ($query->posts as $post) {
            $items = [];
            foreach (['name' => 'Nome', 'email' => 'Email', 'company' => 'Azienda', 'phone' => 'Telefono', 'team_size' => 'Persone sul lead', 'monthly_leads' => 'Lead mensili', 'sources' => 'Provenienza lead', 'message' => 'Messaggio', 'privacy_at' => 'Consenso privacy'] as $key => $label) $items[] = ['name' => $label, 'value' => (string) get_post_meta($post->ID, '_cai_'.$key, true)];
            $data[] = ['group_id' => 'commerciale-ai-forms', 'group_label' => __('Richieste Commerciale AI', 'commerciale-ai-forms'), 'item_id' => 'cai-inquiry-'.$post->ID, 'data' => $items];
        }
        return ['data' => $data, 'done' => $page >= (int) $query->max_num_pages];
    }

    public static function erase_personal_data(string $email, int $page = 1): array
    {
        $ids = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'private', 'posts_per_page' => 50, 'fields' => 'ids', 'meta_key' => '_cai_email', 'meta_value' => sanitize_email($email)]);
        $removed = 0;
        foreach ($ids as $id) if (wp_delete_post((int) $id, true)) $removed++;
        return ['items_removed' => $removed > 0, 'items_retained' => false, 'messages' => [], 'done' => count($ids) < 50];
    }

    private static function type_label(string $type): string
    {
        return match ($type) {'sales' => __('Richiesta commerciale', 'commerciale-ai-forms'), 'support' => __('Assistenza', 'commerciale-ai-forms'), default => __('Contatto', 'commerciale-ai-forms')};
    }

    private static function status_label(string $status): string
    {
        return match ($status) {'working' => __('In lavorazione', 'commerciale-ai-forms'), 'closed' => __('Chiusa', 'commerciale-ai-forms'), default => __('Nuova', 'commerciale-ai-forms')};
    }

    private static function ga4_event(): string
    {
        return self::sanitize_event(get_option('cai_forms_ga4_event', 'generate_lead'));
    }

    private static function current_url(): string
    {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
        $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        return esc_url_raw($scheme.'://'.$host.$uri);
    }

    private static function return_url(string $type): string
    {
        $fallback = home_url('/contatti/');
        $candidate = esc_url_raw(wp_unslash($_POST['cai_return'] ?? ''));
        return remove_query_arg(['cai_form', 'cai_form_type'], wp_validate_redirect($candidate, $fallback));
    }

    private static function request_fingerprint(): string
    {
        return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')).'|'.sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    private static function redirect(string $url, string $status, string $type): never
    {
        wp_safe_redirect(add_query_arg(['cai_form' => $status, 'cai_form_type' => $type], $url).'#commerciale-ai-form');
        exit;
    }
}

register_activation_hook(__FILE__, [Commerciale_AI_Forms::class, 'activate']);
register_deactivation_hook(__FILE__, [Commerciale_AI_Forms::class, 'deactivate']);
Commerciale_AI_Forms::boot();
