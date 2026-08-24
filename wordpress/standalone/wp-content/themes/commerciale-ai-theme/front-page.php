<?php
defined('ABSPATH') || exit;
get_header();
?>
<main>
    <section class="hero">
        <div class="cai-container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><?php esc_html_e('Vendite più lucide, ogni giorno', 'commerciale-ai'); ?></p>
                <h1><?php esc_html_e('Ogni lead merita una', 'commerciale-ai'); ?> <em><?php esc_html_e('risposta.', 'commerciale-ai'); ?></em></h1>
                <p><?php esc_html_e('Commerciale AI raccoglie le richieste, ordina le priorità e prepara il prossimo passo. Il tuo team vende; il lavoro ripetitivo resta a noi.', 'commerciale-ai'); ?></p>
                <div class="hero-actions">
                    <a class="cai-button cai-button--coral" href="#prezzi"><?php esc_html_e('Scopri i piani', 'commerciale-ai'); ?> →</a>
                    <a class="cai-button cai-button--ghost" href="#come-funziona"><?php esc_html_e('Guarda come funziona', 'commerciale-ai'); ?></a>
                </div>
                <div class="hero-proof">
                    <span><?php esc_html_e('Attivazione rapida', 'commerciale-ai'); ?></span>
                    <span><?php esc_html_e('Nessuna carta salvata sul sito', 'commerciale-ai'); ?></span>
                    <span><?php esc_html_e('Supporto italiano', 'commerciale-ai'); ?></span>
                </div>
            </div>
            <div class="product-stage" aria-label="<?php esc_attr_e('Anteprima del software Commerciale AI', 'commerciale-ai'); ?>">
                <div class="product-window">
                    <div class="window-bar"><i></i><i></i><i></i></div>
                    <div class="mock-shell">
                        <div class="mock-nav"><b></b><span></span><span></span><span></span><span></span></div>
                        <div class="mock-content">
                            <h4><?php esc_html_e('Buongiorno, Martina', 'commerciale-ai'); ?></h4>
                            <div class="metric-row">
                                <div class="metric"><strong>24</strong><small><?php esc_html_e('Nuovi lead', 'commerciale-ai'); ?></small></div>
                                <div class="metric"><strong>8</strong><small><?php esc_html_e('Da richiamare', 'commerciale-ai'); ?></small></div>
                                <div class="metric"><strong>71%</strong><small><?php esc_html_e('Risposte', 'commerciale-ai'); ?></small></div>
                            </div>
                            <div class="pipeline">
                                <div class="pipeline-col"><span><?php esc_html_e('Nuovi', 'commerciale-ai'); ?></span><div class="deal"><b>Studio Arco</b>Preventivo sito</div><div class="deal"><b>Alba Srl</b>Consulenza</div></div>
                                <div class="pipeline-col"><span><?php esc_html_e('In contatto', 'commerciale-ai'); ?></span><div class="deal"><b>Nova Lab</b>Demo fissata</div></div>
                                <div class="pipeline-col"><span><?php esc_html_e('Proposta', 'commerciale-ai'); ?></span><div class="deal"><b>Linea Verde</b>€ 4.800</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section section--paper" id="funzioni">
        <div class="cai-container">
            <div class="section-heading">
                <h2><?php esc_html_e('Meno inseguimenti. Più conversazioni che contano.', 'commerciale-ai'); ?></h2>
                <p><?php esc_html_e('Un unico spazio operativo per non perdere richieste, contesto e occasioni commerciali.', 'commerciale-ai'); ?></p>
            </div>
            <div class="feature-grid">
                <article class="feature-card"><span class="feature-number">01</span><h3><?php esc_html_e('Lead in ordine', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Richieste da moduli ed email diventano schede pulite, senza copia-incolla.', 'commerciale-ai'); ?></p></article>
                <article class="feature-card"><span class="feature-number">02</span><h3><?php esc_html_e('Priorità chiare', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Scoring e segnali concreti aiutano il team a capire chi contattare per primo.', 'commerciale-ai'); ?></p></article>
                <article class="feature-card"><span class="feature-number">03</span><h3><?php esc_html_e('Risposte assistite', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Bozze contestuali pronte da rivedere: veloci, coerenti e sempre sotto il tuo controllo.', 'commerciale-ai'); ?></p></article>
                <article class="feature-card"><span class="feature-number">04</span><h3><?php esc_html_e('Pipeline visibile', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Ogni trattativa ha uno stato, un responsabile e una prossima azione.', 'commerciale-ai'); ?></p></article>
                <article class="feature-card"><span class="feature-number">05</span><h3><?php esc_html_e('Preventivi più rapidi', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Dati e contesto del cliente accompagnano la proposta, senza ripartire da zero.', 'commerciale-ai'); ?></p></article>
                <article class="feature-card"><span class="feature-number">06</span><h3><?php esc_html_e('Controllo dei consumi', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Licenze, utenti e soglie mensili restano trasparenti per ogni piano.', 'commerciale-ai'); ?></p></article>
            </div>
        </div>
    </section>

    <section class="section" id="come-funziona">
        <div class="cai-container">
            <div class="section-heading"><h2><?php esc_html_e('Da richiesta a opportunità, in tre passaggi.', 'commerciale-ai'); ?></h2></div>
            <div class="steps">
                <article class="step"><h3><?php esc_html_e('Collega le fonti', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Fai arrivare moduli ed email nello stesso ambiente commerciale.', 'commerciale-ai'); ?></p></article>
                <article class="step"><h3><?php esc_html_e('Lascia lavorare l’AI', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Il sistema deduplica, analizza e propone priorità e risposte.', 'commerciale-ai'); ?></p></article>
                <article class="step"><h3><?php esc_html_e('Decidi e vendi', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Il team approva, personalizza e porta avanti la relazione.', 'commerciale-ai'); ?></p></article>
            </div>
        </div>
    </section>

    <section class="section section--paper pricing-wrap" id="prezzi">
        <div class="cai-container">
            <div class="section-heading"><h2><?php esc_html_e('Un piano per il ritmo della tua squadra.', 'commerciale-ai'); ?></h2><p><?php esc_html_e('Fatturazione annuale sicura tramite Stripe. Puoi gestire rinnovo e disdetta dalla tua area cliente.', 'commerciale-ai'); ?></p></div>
            <?php if (shortcode_exists('commerciale_ai_pricing')) : ?>
                <?php echo do_shortcode('[commerciale_ai_pricing]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else : ?>
                <p><?php esc_html_e('Attiva il plugin Commerciale AI Client Area per mostrare i piani.', 'commerciale-ai'); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="section">
        <div class="cai-container cta-band">
            <div><h2><?php esc_html_e('Il prossimo cliente è già da qualche parte. Non lasciarlo aspettare.', 'commerciale-ai'); ?></h2><p><?php esc_html_e('Scegli il piano adatto e attiva il tuo spazio Commerciale AI.', 'commerciale-ai'); ?></p></div>
            <a class="cai-button cai-button--coral" href="#prezzi"><?php esc_html_e('Inizia ora', 'commerciale-ai'); ?> →</a>
        </div>
    </section>
</main>
<?php get_footer(); ?>
