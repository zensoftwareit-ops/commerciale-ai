<?php
/**
 * Plugin Name: Commerciale AI Client Area
 * Description: Registrazione, listino, Stripe Checkout, Customer Portal e provisioning licenze Commerciale AI.
 * Version: 1.1.0
 * Author: Zen Software
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: commerciale-ai-client
 * License: MIT
 */

defined('ABSPATH') || exit;

final class Commerciale_AI_Client_Area
{
    private const VERSION = '1.1.0';
    private const OPTION_KEYS = ['cai_api_base_url', 'cai_api_key', 'cai_stripe_secret_key', 'cai_stripe_webhook_secret', 'cai_software_url'];
    private const SECRET_KEYS = ['cai_api_key', 'cai_stripe_secret_key', 'cai_stripe_webhook_secret'];
    private const ACTIVE_STATUSES = ['active', 'trialing'];
    private const KNOWN_STATUSES = ['active', 'trialing', 'past_due', 'unpaid', 'canceled', 'paused', 'suspended'];

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_shortcode('commerciale_ai_pricing', [self::class, 'pricing_shortcode']);
        add_shortcode('commerciale_ai_account', [self::class, 'account_shortcode']);
        add_action('admin_post_cai_checkout', [self::class, 'checkout']);
        add_action('admin_post_cai_portal', [self::class, 'portal']);
        add_action('admin_post_cai_profile', [self::class, 'profile']);
        add_action('admin_post_cai_logout', [self::class, 'logout']);
        add_action('admin_post_cai_test_connection', [self::class, 'test_connection']);
        add_action('admin_post_nopriv_cai_register', [self::class, 'register']);
        add_action('admin_post_nopriv_cai_login', [self::class, 'login']);
        add_action('rest_api_init', static function (): void {
            register_rest_route('commerciale-ai/v1', '/stripe-webhook', [
                'methods' => 'POST',
                'callback' => [self::class, 'stripe_webhook'],
                'permission_callback' => '__return_true',
            ]);
        });
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), [self::class, 'settings_link']);
    }

    public static function activate(): void
    {
        self::maybe_create_page('cai_pricing_page_id', 'prezzi', 'Prezzi', '[commerciale_ai_pricing]');
        self::maybe_create_page('cai_account_page_id', 'area-cliente', 'Area cliente', '[commerciale_ai_account]');
        update_option('cai_plugin_version', self::VERSION, false);
        flush_rewrite_rules(false);
    }

    private static function maybe_create_page(string $option, string $slug, string $title, string $content): void
    {
        $page_id = (int) get_option($option);
        if ($page_id > 0 && get_post($page_id)) return;
        $existing = get_page_by_path($slug);
        if ($existing instanceof WP_Post) {
            update_option($option, $existing->ID, false);
            return;
        }
        $page_id = wp_insert_post(['post_title' => $title, 'post_name' => $slug, 'post_content' => $content, 'post_status' => 'publish', 'post_type' => 'page'], true);
        if (! is_wp_error($page_id)) update_option($option, $page_id, false);
    }

    public static function settings_link(array $links): array
    {
        array_unshift($links, '<a href="'.esc_url(admin_url('options-general.php?page=commerciale-ai-client')).'">'.esc_html__('Impostazioni', 'commerciale-ai-client').'</a>');
        return $links;
    }

    public static function register_assets(): void
    {
        wp_register_style('commerciale-ai-client', plugins_url('assets/client-area.css', __FILE__), [], self::VERSION);
        wp_register_script('commerciale-ai-client', plugins_url('assets/client-area.js', __FILE__), [], self::VERSION, true);
    }

    private static function enqueue_assets(): void
    {
        wp_enqueue_style('commerciale-ai-client');
        wp_enqueue_script('commerciale-ai-client');
    }

    public static function admin_menu(): void
    {
        add_options_page('Commerciale AI', 'Commerciale AI', 'manage_options', 'commerciale-ai-client', [self::class, 'settings_page']);
    }

    public static function register_settings(): void
    {
        foreach (self::OPTION_KEYS as $key) {
            register_setting('cai_settings', $key, [
                'type' => 'string',
                'sanitize_callback' => static function ($value) use ($key): string {
                    $value = is_string($value) ? trim(wp_unslash($value)) : '';
                    if (in_array($key, self::SECRET_KEYS, true) && $value === '') return (string) get_option($key, '');
                    return str_contains($key, '_url') ? esc_url_raw($value) : sanitize_text_field($value);
                },
            ]);
        }
    }

    public static function settings_page(): void
    {
        if (! current_user_can('manage_options')) return;
        $fields = [
            'cai_api_base_url' => ['URL API Commerciale AI', 'url', 'https://app.example.it'],
            'cai_api_key' => ['Chiave integrazione billing', 'password', 'Lascia vuoto per mantenere quella salvata'],
            'cai_stripe_secret_key' => ['Stripe Secret Key', 'password', 'sk_live_… oppure sk_test_…'],
            'cai_stripe_webhook_secret' => ['Stripe Webhook Secret', 'password', 'whsec_…'],
            'cai_software_url' => ['URL accesso software', 'url', 'https://app.example.it/login'],
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Commerciale AI · Vendita e licenze', 'commerciale-ai-client'); ?></h1>
            <?php self::admin_notice(); ?>
            <p><?php esc_html_e('Configura il collegamento tra WordPress, Stripe e il backend Commerciale AI.', 'commerciale-ai-client'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('cai_settings'); ?>
                <table class="form-table" role="presentation">
                    <?php foreach ($fields as $key => [$label, $type, $placeholder]) : ?>
                        <tr><th scope="row"><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td>
                            <input class="regular-text" type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo $type === 'password' ? '' : esc_attr(get_option($key)); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" autocomplete="new-password">
                            <?php if ($type === 'password' && get_option($key)) : ?><p class="description">✓ <?php esc_html_e('Valore già configurato', 'commerciale-ai-client'); ?></p><?php endif; ?>
                        </td></tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2><?php esc_html_e('Verifica configurazione', 'commerciale-ai-client'); ?></h2>
            <p><strong><?php esc_html_e('Webhook Stripe:', 'commerciale-ai-client'); ?></strong> <code><?php echo esc_html(rest_url('commerciale-ai/v1/stripe-webhook')); ?></code></p>
            <p><strong><?php esc_html_e('Pagina prezzi:', 'commerciale-ai-client'); ?></strong> <a href="<?php echo esc_url(self::page_url('cai_pricing_page_id', '/prezzi/')); ?>"><?php echo esc_html(self::page_url('cai_pricing_page_id', '/prezzi/')); ?></a><br>
            <strong><?php esc_html_e('Area cliente:', 'commerciale-ai-client'); ?></strong> <a href="<?php echo esc_url(self::account_url()); ?>"><?php echo esc_html(self::account_url()); ?></a></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="cai_test_connection"><?php wp_nonce_field('cai_test_connection'); ?><?php submit_button(__('Testa API e Stripe', 'commerciale-ai-client'), 'secondary', 'submit', false); ?></form>
            <p class="description"><?php esc_html_e('Eventi Stripe richiesti: checkout.session.completed, customer.subscription.created, customer.subscription.updated e customer.subscription.deleted.', 'commerciale-ai-client'); ?></p>
        </div>
        <?php
    }

    private static function admin_notice(): void
    {
        $status = sanitize_key($_GET['cai_status'] ?? '');
        if ($status === '') return;
        $ok = $status === 'connections_ok';
        $message = $ok ? __('Collegamento API e Stripe verificato.', 'commerciale-ai-client') : __('Verifica non riuscita. Controlla URL e credenziali.', 'commerciale-ai-client');
        echo '<div class="notice notice-'.($ok ? 'success' : 'error').' is-dismissible"><p>'.esc_html($message).'</p></div>';
    }

    public static function test_connection(): void
    {
        if (! current_user_can('manage_options')) wp_die('Operazione non consentita.');
        check_admin_referer('cai_test_connection');
        $api = self::api('GET', '/api/v1/billing/plans', null, false);
        $stripe = self::stripe('GET', '/v1/account');
        $status = ! is_wp_error($api) && ! is_wp_error($stripe) ? 'connections_ok' : 'connections_error';
        wp_safe_redirect(add_query_arg(['page' => 'commerciale-ai-client', 'cai_status' => $status], admin_url('options-general.php')));
        exit;
    }

    public static function pricing_shortcode(): string
    {
        self::enqueue_assets();
        $response = self::api('GET', '/api/v1/billing/plans');
        if (is_wp_error($response)) return self::message('error', __('Il listino non è temporaneamente disponibile. Riprova tra poco.', 'commerciale-ai-client'));
        $plans = $response['data'] ?? [];
        if (! is_array($plans) || $plans === []) return self::message('info', __('I piani saranno disponibili a breve.', 'commerciale-ai-client'));
        $current_status = is_user_logged_in() ? (string) get_user_meta(get_current_user_id(), 'cai_license_status', true) : '';
        ob_start();
        echo '<div class="cai-plans">';
        foreach ($plans as $index => $plan) {
            if (! is_array($plan)) continue;
            $featured = count($plans) === 3 && $index === 1;
            echo '<article class="cai-plan'.($featured ? ' cai-plan--featured' : '').'">';
            if ($featured) echo '<span class="cai-plan__badge">'.esc_html__('Consigliato', 'commerciale-ai-client').'</span>';
            $price = (int) ($plan['annual_price_cents'] ?? 0);
            $currency = (string) ($plan['currency'] ?? 'EUR');
            echo '<p class="cai-plan__name">'.esc_html($plan['name'] ?? '').'</p><p class="cai-plan__fit">'.esc_html(self::plan_fit($plan)).'</p><p class="cai-plan__description">'.esc_html($plan['description'] ?? '').'</p>';
            echo '<div class="cai-plan__price"><strong>'.esc_html(self::money($price, $currency)).'</strong><span>/'.esc_html__('anno', 'commerciale-ai-client').'</span></div>';
            if ($price > 0) echo '<p class="cai-plan__monthly">'.esc_html(sprintf(__('Equivale a %s/mese, con fatturazione annuale.', 'commerciale-ai-client'), self::money((int) round($price / 12), $currency))).'</p>';
            echo '<ul class="cai-plan__features">';
            $seats = max(1, (int) ($plan['seat_limit'] ?? 1));
            echo '<li><strong>'.esc_html(sprintf(_n('%d utente incluso', '%d utenti inclusi', $seats, 'commerciale-ai-client'), $seats)).'</strong></li>';
            echo '<li>'.esc_html(self::limit_label($plan['monthly_lead_limit'] ?? null, __('lead al mese', 'commerciale-ai-client'), __('Lead senza soglia mensile di piano', 'commerciale-ai-client'))).'</li>';
            echo '<li>'.esc_html(self::limit_label($plan['monthly_ai_token_limit'] ?? null, __('token AI al mese', 'commerciale-ai-client'), __('AI senza soglia mensile di piano', 'commerciale-ai-client'))).'</li>';
            foreach ($plan['features'] ?? [] as $feature) echo '<li>'.esc_html((string) $feature).'</li>';
            echo '</ul>';
            if (! is_user_logged_in()) {
                echo '<a class="cai-action cai-action--primary" href="'.esc_url(add_query_arg('plan', sanitize_key($plan['slug'] ?? ''), self::account_url())).'">'.esc_html__('Crea account', 'commerciale-ai-client').'</a>';
            } elseif (in_array($current_status, self::ACTIVE_STATUSES, true)) {
                echo '<a class="cai-action cai-action--secondary" href="'.esc_url(self::account_url()).'">'.esc_html__('Gestisci abbonamento', 'commerciale-ai-client').'</a>';
            } elseif (! empty($plan['purchasable'])) {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="cai_checkout"><input type="hidden" name="plan_slug" value="'.esc_attr($plan['slug'] ?? '').'">';
                wp_nonce_field('cai_checkout_'.sanitize_key($plan['slug'] ?? ''));
                echo '<button class="cai-action cai-action--primary" type="submit">'.esc_html(sprintf(__('Scegli %s', 'commerciale-ai-client'), $plan['name'] ?? '')).'</button></form>';
            } else echo '<span class="cai-action cai-action--disabled">'.esc_html__('Prossimamente', 'commerciale-ai-client').'</span>';
            echo '</article>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function plan_fit(array $plan): string
    {
        $seats = max(1, (int) ($plan['seat_limit'] ?? 1));
        if ($seats === 1) return __('Per professionisti e attività individuali', 'commerciale-ai-client');
        if ($seats <= 3) return __('Per piccoli team che condividono i lead', 'commerciale-ai-client');
        return __('Per team commerciali strutturati', 'commerciale-ai-client');
    }

    private static function limit_label(mixed $value, string $suffix, string $unlimited): string
    {
        if ($value === null || $value === '') return $unlimited;
        return number_format_i18n(max(0, (int) $value)).' '.$suffix;
    }

    public static function account_shortcode(): string
    {
        self::enqueue_assets();
        if (! is_user_logged_in()) return self::auth_forms();
        $user = wp_get_current_user();
        self::refresh_license($user->ID);
        $status = (string) get_user_meta($user->ID, 'cai_license_status', true);
        $plan = (string) get_user_meta($user->ID, 'cai_plan_name', true);
        $key = (string) get_user_meta($user->ID, 'cai_license_key', true);
        $period_end = (string) get_user_meta($user->ID, 'cai_license_period_end', true);
        $canceling = (bool) get_user_meta($user->ID, 'cai_cancel_at_period_end', true);
        ob_start(); self::checkout_notice(); ?>
        <div class="cai-account">
            <div class="cai-account__header"><div><span class="cai-kicker"><?php esc_html_e('Area cliente', 'commerciale-ai-client'); ?></span><h2><?php echo esc_html($user->display_name); ?></h2></div><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="cai_logout"><?php wp_nonce_field('cai_logout'); ?><button class="cai-link-button" type="submit"><?php esc_html_e('Esci', 'commerciale-ai-client'); ?></button></form></div>
            <div class="cai-account__grid">
                <section class="cai-panel"><h3><?php esc_html_e('Abbonamento', 'commerciale-ai-client'); ?></h3>
                    <div class="cai-subscription"><div><span><?php esc_html_e('Piano', 'commerciale-ai-client'); ?></span><strong><?php echo esc_html($plan ?: __('Nessun piano', 'commerciale-ai-client')); ?></strong></div><div><span><?php esc_html_e('Stato', 'commerciale-ai-client'); ?></span><strong class="cai-status cai-status--<?php echo esc_attr($status ?: 'none'); ?>"><?php echo esc_html(self::status_label($status)); ?></strong></div><?php if ($period_end) : ?><div><span><?php echo esc_html($canceling ? __('Termina il', 'commerciale-ai-client') : __('Prossimo rinnovo', 'commerciale-ai-client')); ?></span><strong><?php echo esc_html(self::date_label($period_end)); ?></strong></div><?php endif; ?></div>
                    <?php if ($key) : ?><p class="cai-license"><span><?php esc_html_e('Codice licenza', 'commerciale-ai-client'); ?></span><code><?php echo esc_html($key); ?></code></p><?php endif; ?>
                    <div class="cai-actions"><?php if (get_user_meta($user->ID, 'cai_stripe_customer_id', true)) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="cai_portal"><?php wp_nonce_field('cai_portal'); ?><button class="cai-action cai-action--secondary" type="submit"><?php esc_html_e('Pagamenti, fatture e disdetta', 'commerciale-ai-client'); ?></button></form><?php endif; ?><?php if (in_array($status, self::ACTIVE_STATUSES, true) && get_option('cai_software_url')) : ?><a class="cai-action cai-action--primary" href="<?php echo esc_url(get_option('cai_software_url')); ?>"><?php esc_html_e('Apri Commerciale AI', 'commerciale-ai-client'); ?> →</a><?php endif; ?><?php if (! in_array($status, self::ACTIVE_STATUSES, true)) : ?><a class="cai-action cai-action--primary" href="<?php echo esc_url(self::page_url('cai_pricing_page_id', '/#prezzi')); ?>"><?php esc_html_e('Scegli un piano', 'commerciale-ai-client'); ?></a><?php endif; ?></div>
                </section>
                <section class="cai-panel"><h3><?php esc_html_e('Dati account', 'commerciale-ai-client'); ?></h3><form class="cai-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="cai_profile"><?php wp_nonce_field('cai_profile'); ?><label><?php esc_html_e('Nome e cognome', 'commerciale-ai-client'); ?><input name="display_name" required maxlength="255" value="<?php echo esc_attr($user->display_name); ?>"></label><label><?php esc_html_e('Azienda', 'commerciale-ai-client'); ?><input name="company" maxlength="255" value="<?php echo esc_attr(get_user_meta($user->ID, 'cai_company', true)); ?>"></label><label><?php esc_html_e('Email', 'commerciale-ai-client'); ?><input type="email" disabled value="<?php echo esc_attr($user->user_email); ?>"></label><button class="cai-action cai-action--secondary" type="submit"><?php esc_html_e('Salva dati', 'commerciale-ai-client'); ?></button></form></section>
            </div>
        </div>
        <?php return (string) ob_get_clean();
    }

    private static function auth_forms(): string
    {
        $selected_plan = sanitize_key($_GET['plan'] ?? '');
        ob_start(); self::auth_notice(); ?>
        <div class="cai-auth-grid">
            <form class="cai-auth-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="cai_register"><input type="hidden" name="plan" value="<?php echo esc_attr($selected_plan); ?>"><?php wp_nonce_field('cai_register'); ?><span class="cai-kicker"><?php esc_html_e('Nuovo cliente', 'commerciale-ai-client'); ?></span><h2><?php esc_html_e('Crea il tuo account', 'commerciale-ai-client'); ?></h2><div class="cai-form"><label><?php esc_html_e('Nome e cognome', 'commerciale-ai-client'); ?><input name="name" required maxlength="255" autocomplete="name"></label><label><?php esc_html_e('Azienda', 'commerciale-ai-client'); ?><input name="company" maxlength="255" autocomplete="organization"></label><label><?php esc_html_e('Email', 'commerciale-ai-client'); ?><input type="email" name="email" required maxlength="255" autocomplete="email"></label><label><?php esc_html_e('Password', 'commerciale-ai-client'); ?><input type="password" name="password" minlength="12" required autocomplete="new-password"><small><?php esc_html_e('Almeno 12 caratteri.', 'commerciale-ai-client'); ?></small></label><button class="cai-action cai-action--primary" type="submit"><?php esc_html_e('Crea account', 'commerciale-ai-client'); ?></button></div></form>
            <form class="cai-auth-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="cai_login"><?php wp_nonce_field('cai_login'); ?><span class="cai-kicker"><?php esc_html_e('Già cliente', 'commerciale-ai-client'); ?></span><h2><?php esc_html_e('Accedi', 'commerciale-ai-client'); ?></h2><div class="cai-form"><label><?php esc_html_e('Email', 'commerciale-ai-client'); ?><input type="email" name="email" required autocomplete="email"></label><label><?php esc_html_e('Password', 'commerciale-ai-client'); ?><input type="password" name="password" required autocomplete="current-password"></label><label class="cai-check"><input type="checkbox" name="remember" value="1"> <?php esc_html_e('Resta connesso', 'commerciale-ai-client'); ?></label><button class="cai-action cai-action--secondary" type="submit"><?php esc_html_e('Accedi', 'commerciale-ai-client'); ?></button><a class="cai-small-link" href="<?php echo esc_url(wp_lostpassword_url(self::account_url())); ?>"><?php esc_html_e('Password dimenticata?', 'commerciale-ai-client'); ?></a></div></form>
        </div>
        <?php return (string) ob_get_clean();
    }

    private static function auth_notice(): void
    {
        $error = sanitize_key($_GET['auth_error'] ?? '');
        if ($error === '') return;
        $message = $error === 'existing' ? __('Esiste già un account con questa email. Accedi dal modulo qui sotto.', 'commerciale-ai-client') : __('Credenziali non valide. Riprova.', 'commerciale-ai-client');
        echo self::message('error', $message);
    }

    private static function checkout_notice(): void
    {
        $state = sanitize_key($_GET['checkout'] ?? '');
        if ($state === 'success') echo self::message('success', __('Pagamento ricevuto. La licenza comparirà non appena Stripe completa la conferma.', 'commerciale-ai-client'));
        if ($state === 'cancelled') echo self::message('info', __('Pagamento annullato: non è stato effettuato alcun addebito.', 'commerciale-ai-client'));
        if (sanitize_key($_GET['updated'] ?? '') === '1') echo self::message('success', __('Dati account aggiornati.', 'commerciale-ai-client'));
    }

    public static function register(): void
    {
        check_admin_referer('cai_register');
        $email = sanitize_email(wp_unslash($_POST['email'] ?? '')); $name = sanitize_text_field(wp_unslash($_POST['name'] ?? '')); $company = sanitize_text_field(wp_unslash($_POST['company'] ?? '')); $password = (string) wp_unslash($_POST['password'] ?? ''); $plan = sanitize_key($_POST['plan'] ?? '');
        if (! is_email($email) || $name === '' || strlen($password) < 12) self::auth_redirect('invalid', $plan);
        if (email_exists($email)) self::auth_redirect('existing', $plan);
        $id = wp_create_user($email, $password, $email);
        if (is_wp_error($id)) self::auth_redirect('invalid', $plan);
        wp_update_user(['ID' => $id, 'display_name' => $name, 'first_name' => $name]); update_user_meta($id, 'cai_company', $company);
        wp_set_current_user($id); wp_set_auth_cookie($id, true, is_ssl());
        wp_safe_redirect($plan ? add_query_arg('plan', $plan, self::page_url('cai_pricing_page_id', '/#prezzi')) : self::account_url()); exit;
    }

    public static function login(): void
    {
        check_admin_referer('cai_login');
        $user = wp_signon(['user_login' => sanitize_email(wp_unslash($_POST['email'] ?? '')), 'user_password' => (string) wp_unslash($_POST['password'] ?? ''), 'remember' => ! empty($_POST['remember'])], is_ssl());
        if (is_wp_error($user)) self::auth_redirect('invalid');
        wp_safe_redirect(self::account_url()); exit;
    }

    private static function auth_redirect(string $error, string $plan = ''): never
    {
        $args = ['auth_error' => $error]; if ($plan !== '') $args['plan'] = $plan;
        wp_safe_redirect(add_query_arg($args, self::account_url())); exit;
    }

    public static function logout(): void
    {
        check_admin_referer('cai_logout'); wp_logout(); wp_safe_redirect(self::account_url()); exit;
    }

    public static function profile(): void
    {
        if (! is_user_logged_in()) auth_redirect(); check_admin_referer('cai_profile'); $id = get_current_user_id();
        $name = sanitize_text_field(wp_unslash($_POST['display_name'] ?? '')); $company = sanitize_text_field(wp_unslash($_POST['company'] ?? ''));
        if ($name === '') wp_die('Il nome è obbligatorio.');
        wp_update_user(['ID' => $id, 'display_name' => $name]); update_user_meta($id, 'cai_company', $company);
        if (get_user_meta($id, 'cai_license_key', true)) self::api('PATCH', '/api/v1/billing/accounts/'.rawurlencode(self::external_id($id)), ['name' => $name, 'company' => $company], false);
        wp_safe_redirect(add_query_arg('updated', '1', self::account_url())); exit;
    }

    public static function checkout(): void
    {
        if (! is_user_logged_in()) auth_redirect(); $slug = sanitize_key($_POST['plan_slug'] ?? ''); check_admin_referer('cai_checkout_'.$slug); $user_id = get_current_user_id();
        $existing_status = (string) get_user_meta($user_id, 'cai_license_status', true);
        $existing_subscription = (string) get_user_meta($user_id, 'cai_stripe_subscription_id', true);
        if (! in_array($existing_status, ['', 'canceled', 'suspended'], true) || ($existing_status === '' && $existing_subscription !== '')) { wp_safe_redirect(self::account_url()); exit; }
        $plans = self::api('GET', '/api/v1/billing/plans', null, false); if (is_wp_error($plans)) wp_die('Listino non disponibile.');
        $plan = null; foreach ($plans['data'] ?? [] as $candidate) if (($candidate['slug'] ?? '') === $slug) $plan = $candidate;
        if (! is_array($plan) || empty($plan['stripe_price_id']) || empty($plan['purchasable'])) wp_die('Pacchetto non acquistabile.');
        $user = wp_get_current_user(); $customer = (string) get_user_meta($user->ID, 'cai_stripe_customer_id', true);
        $body = ['mode' => 'subscription', 'line_items[0][price]' => $plan['stripe_price_id'], 'line_items[0][quantity]' => 1, 'success_url' => add_query_arg('checkout', 'success', self::account_url()).'&session_id={CHECKOUT_SESSION_ID}', 'cancel_url' => add_query_arg('checkout', 'cancelled', self::page_url('cai_pricing_page_id', '/#prezzi')), 'client_reference_id' => self::external_id($user->ID), 'allow_promotion_codes' => 'true', 'metadata[wp_user_id]' => $user->ID, 'metadata[plan_slug]' => $slug, 'subscription_data[metadata][wp_user_id]' => $user->ID, 'subscription_data[metadata][plan_slug]' => $slug];
        if ($customer !== '') $body['customer'] = $customer; else $body['customer_email'] = $user->user_email;
        $idempotency = 'checkout-'.hash('sha256', self::external_id($user->ID).'|'.$slug.'|'.gmdate('Y-m-d-H'));
        $session = self::stripe('POST', '/v1/checkout/sessions', $body, $idempotency);
        if (is_wp_error($session) || empty($session['url'])) wp_die('Impossibile avviare il pagamento. Verifica la configurazione Stripe.');
        wp_redirect(esc_url_raw($session['url'])); exit;
    }

    public static function portal(): void
    {
        if (! is_user_logged_in()) auth_redirect(); check_admin_referer('cai_portal'); $customer = (string) get_user_meta(get_current_user_id(), 'cai_stripe_customer_id', true);
        if ($customer === '') wp_die('Cliente Stripe non disponibile.');
        $session = self::stripe('POST', '/v1/billing_portal/sessions', ['customer' => $customer, 'return_url' => self::account_url()]);
        if (is_wp_error($session) || empty($session['url'])) wp_die('Portale pagamenti non disponibile.'); wp_redirect(esc_url_raw($session['url'])); exit;
    }

    public static function stripe_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_body(); $signature = (string) $request->get_header('stripe-signature');
        if (! self::valid_stripe_signature($payload, $signature)) return new WP_REST_Response(['message' => 'Invalid signature'], 400);
        $event = json_decode($payload, true);
        if (! is_array($event) || empty($event['id']) || empty($event['type'])) return new WP_REST_Response(['message' => 'Invalid payload'], 400);
        $type = (string) $event['type'];
        if (! str_starts_with($type, 'customer.subscription.') && $type !== 'checkout.session.completed') return new WP_REST_Response(['received' => true]);
        $object = $event['data']['object'] ?? [];
        if ($type === 'checkout.session.completed' && ! empty($object['subscription'])) $object = self::stripe('GET', '/v1/subscriptions/'.rawurlencode(is_array($object['subscription']) ? ($object['subscription']['id'] ?? '') : $object['subscription']));
        if (is_wp_error($object) || ! is_array($object)) return new WP_REST_Response(['message' => 'Unable to load subscription'], 502);
        if (! self::sync_subscription($object, (string) $event['id'], $type)) return new WP_REST_Response(['message' => 'Provisioning failed'], 500);
        return new WP_REST_Response(['received' => true]);
    }

    private static function sync_subscription(array $subscription, string $event_id, string $event_type): bool
    {
        $metadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : []; $user_id = absint($metadata['wp_user_id'] ?? 0);
        $customer_id = is_array($subscription['customer'] ?? null) ? (string) ($subscription['customer']['id'] ?? '') : (string) ($subscription['customer'] ?? ''); $subscription_id = (string) ($subscription['id'] ?? '');
        if ($user_id === 0 && $subscription_id !== '') $user_id = self::find_user_by_meta('cai_stripe_subscription_id', $subscription_id);
        if ($user_id === 0 && $customer_id !== '') $user_id = self::find_user_by_meta('cai_stripe_customer_id', $customer_id);
        $user = $user_id > 0 ? get_user_by('id', $user_id) : false; if (! $user instanceof WP_User) return false;
        $price_id = (string) ($subscription['items']['data'][0]['price']['id'] ?? ''); $slug = sanitize_key($metadata['plan_slug'] ?? '');
        if ($slug === '' && $price_id !== '') $slug = self::plan_slug_for_price($price_id);
        if ($slug === '') $slug = sanitize_key(get_user_meta($user_id, 'cai_plan_slug', true)); if ($slug === '') return false;
        $status = (string) ($subscription['status'] ?? 'suspended'); if (! in_array($status, self::KNOWN_STATUSES, true)) $status = 'suspended';
        $period_timestamp = $subscription['current_period_end'] ?? ($subscription['items']['data'][0]['current_period_end'] ?? null);
        $result = self::api('POST', '/api/v1/billing/provision', ['event_id' => $event_id, 'event_type' => $event_type, 'external_account_id' => self::external_id($user_id), 'email' => $user->user_email, 'name' => $user->display_name, 'company' => get_user_meta($user_id, 'cai_company', true), 'plan_slug' => $slug, 'stripe_price_id' => $price_id ?: null, 'stripe_customer_id' => $customer_id ?: null, 'stripe_subscription_id' => $subscription_id ?: null, 'status' => $status, 'starts_at' => ! empty($subscription['start_date']) ? gmdate('c', (int) $subscription['start_date']) : null, 'current_period_ends_at' => $period_timestamp ? gmdate('c', (int) $period_timestamp) : null, 'ends_at' => ! empty($subscription['ended_at']) ? gmdate('c', (int) $subscription['ended_at']) : null, 'cancel_at_period_end' => ! empty($subscription['cancel_at_period_end'])], false);
        if (is_wp_error($result) || empty($result['data'])) return false;
        self::store_license_meta($user_id, $result['data']); update_user_meta($user_id, 'cai_stripe_customer_id', $customer_id); update_user_meta($user_id, 'cai_stripe_subscription_id', $subscription_id); return true;
    }

    private static function refresh_license(int $user_id): void
    {
        $remote = self::api('GET', '/api/v1/billing/accounts/'.rawurlencode(self::external_id($user_id)).'/license', null, false);
        if (! is_wp_error($remote) && ! empty($remote['data'])) self::store_license_meta($user_id, $remote['data']);
    }

    private static function store_license_meta(int $user_id, array $license): void
    {
        update_user_meta($user_id, 'cai_license_status', sanitize_key($license['status'] ?? '')); update_user_meta($user_id, 'cai_plan_name', sanitize_text_field($license['plan']['name'] ?? '')); update_user_meta($user_id, 'cai_plan_slug', sanitize_key($license['plan']['slug'] ?? get_user_meta($user_id, 'cai_plan_slug', true))); update_user_meta($user_id, 'cai_license_key', sanitize_text_field($license['key'] ?? '')); update_user_meta($user_id, 'cai_license_period_end', sanitize_text_field($license['current_period_ends_at'] ?? '')); update_user_meta($user_id, 'cai_cancel_at_period_end', ! empty($license['cancel_at_period_end']));
    }

    private static function find_user_by_meta(string $key, string $value): int
    {
        $users = get_users(['number' => 1, 'fields' => 'ids', 'meta_key' => $key, 'meta_value' => $value]); return (int) ($users[0] ?? 0);
    }

    private static function plan_slug_for_price(string $price_id): string
    {
        $plans = self::api('GET', '/api/v1/billing/plans'); if (is_wp_error($plans)) return '';
        foreach ($plans['data'] ?? [] as $plan) if (($plan['stripe_price_id'] ?? '') === $price_id) return sanitize_key($plan['slug'] ?? ''); return '';
    }

    private static function valid_stripe_signature(string $payload, string $header): bool
    {
        $secret = (string) get_option('cai_stripe_webhook_secret'); if ($secret === '' || $header === '') return false; $parts = [];
        foreach (explode(',', $header) as $part) { [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null); if ($key && $value) $parts[$key][] = $value; }
        $timestamp = (int) ($parts['t'][0] ?? 0); if ($timestamp === 0 || abs(time() - $timestamp) > 300) return false; $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($parts['v1'] ?? [] as $candidate) if (hash_equals($expected, (string) $candidate)) return true; return false;
    }

    private static function external_id(int $user_id): string { return 'wp:'.substr(hash('sha256', home_url('/')), 0, 12).':'.$user_id; }

    private static function api(string $method, string $path, ?array $body = null, bool $cache = true): array|WP_Error
    {
        $base = untrailingslashit((string) get_option('cai_api_base_url')); $key = (string) get_option('cai_api_key');
        if ($base === '' || $key === '') return new WP_Error('cai_not_configured', 'API Commerciale AI non configurata.');
        $cache_key = 'cai_api_'.md5($method.'|'.$path);
        if ($method === 'GET' && $cache) { $cached = get_transient($cache_key); if (is_array($cached)) return $cached; }
        $args = ['method' => $method, 'timeout' => 20, 'redirection' => 2, 'headers' => ['Authorization' => 'Bearer '.$key, 'Accept' => 'application/json']];
        if ($body !== null) { $args['headers']['Content-Type'] = 'application/json'; $args['body'] = wp_json_encode($body); }
        $result = self::decode(wp_remote_request($base.$path, $args));
        if ($method === 'GET' && $cache && ! is_wp_error($result)) set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS); return $result;
    }

    private static function stripe(string $method, string $path, ?array $body = null, string $idempotency_key = ''): array|WP_Error
    {
        $secret = (string) get_option('cai_stripe_secret_key'); if ($secret === '') return new WP_Error('cai_stripe_not_configured', 'Stripe non configurato.');
        $headers = ['Authorization' => 'Bearer '.$secret]; if ($idempotency_key !== '') $headers['Idempotency-Key'] = $idempotency_key;
        $args = ['method' => $method, 'timeout' => 20, 'redirection' => 0, 'headers' => $headers]; if ($body !== null) $args['body'] = $body;
        return self::decode(wp_remote_request('https://api.stripe.com'.$path, $args));
    }

    private static function decode(array|WP_Error $response): array|WP_Error
    {
        if (is_wp_error($response)) return $response; $status = wp_remote_retrieve_response_code($response); $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($status >= 200 && $status < 300 && is_array($data)) return $data;
        $message = is_array($data) ? ($data['message'] ?? $data['error']['message'] ?? 'Errore remoto') : 'Risposta remota non valida'; return new WP_Error('cai_remote_error', sanitize_text_field((string) $message), ['status' => $status]);
    }

    private static function page_url(string $option, string $fallback): string { $page_id = (int) get_option($option); $url = $page_id > 0 ? get_permalink($page_id) : false; return $url ? (string) $url : home_url($fallback); }
    private static function account_url(): string { return self::page_url('cai_account_page_id', '/area-cliente/'); }
    private static function money(int $cents, string $currency): string { $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£']; return ($symbols[strtoupper($currency)] ?? strtoupper($currency).' ').number_format($cents / 100, 0, ',', '.'); }
    private static function status_label(string $status): string { return match ($status) { 'active' => __('Attivo', 'commerciale-ai-client'), 'trialing' => __('Periodo di prova', 'commerciale-ai-client'), 'past_due' => __('Pagamento da regolarizzare', 'commerciale-ai-client'), 'unpaid' => __('Non pagato', 'commerciale-ai-client'), 'canceled' => __('Terminato', 'commerciale-ai-client'), 'paused' => __('In pausa', 'commerciale-ai-client'), 'suspended' => __('Sospeso', 'commerciale-ai-client'), default => __('Non attivo', 'commerciale-ai-client') }; }
    private static function date_label(string $date): string { $timestamp = strtotime($date); return $timestamp ? wp_date(get_option('date_format'), $timestamp) : '—'; }
    private static function message(string $type, string $message): string { return '<div class="cai-message cai-message--'.esc_attr($type).'" role="status">'.esc_html($message).'</div>'; }
}

register_activation_hook(__FILE__, [Commerciale_AI_Client_Area::class, 'activate']);
Commerciale_AI_Client_Area::boot();
