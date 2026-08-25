<?php
defined('ABSPATH') || exit;
get_header();
?>
<main>
    <section class="hero">
        <div class="cai-container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow"><?php esc_html_e('Il lavoro commerciale, finalmente in ordine', 'commerciale-ai'); ?></p>
                <h1><?php esc_html_e('Dal nuovo lead alla', 'commerciale-ai'); ?> <em><?php esc_html_e('prossima azione.', 'commerciale-ai'); ?></em></h1>
                <p><?php esc_html_e('Commerciale AI raccoglie le richieste, comprende il contesto e prepara risposte e follow-up. Il tuo team controlla ogni decisione e non perde più il filo della conversazione.', 'commerciale-ai'); ?></p>
                <div class="hero-actions">
                    <a class="cai-button cai-button--coral" href="<?php echo esc_url(cai_page_url('prezzi')); ?>"><?php esc_html_e('Confronta i piani', 'commerciale-ai'); ?> →</a>
                    <a class="cai-button cai-button--ghost" href="<?php echo esc_url(cai_page_url('come-funziona')); ?>"><?php esc_html_e('Scopri come funziona', 'commerciale-ai'); ?></a>
                </div>
                <div class="hero-proof">
                    <span><?php esc_html_e('AI con controllo umano', 'commerciale-ai'); ?></span>
                    <span><?php esc_html_e('Pagamenti sicuri con Stripe', 'commerciale-ai'); ?></span>
                    <span><?php esc_html_e('Supporto in italiano', 'commerciale-ai'); ?></span>
                </div>
            </div>
            <div class="product-stage" aria-label="<?php esc_attr_e('Anteprima illustrativa della inbox Commerciale AI', 'commerciale-ai'); ?>">
                <div class="product-window">
                    <div class="window-bar"><i></i><i></i><i></i><span><?php esc_html_e('Inbox lead', 'commerciale-ai'); ?></span></div>
                    <div class="mock-shell">
                        <div class="mock-nav"><b></b><span></span><span></span><span></span><span></span></div>
                        <div class="mock-content">
                            <div class="mock-heading"><div><small><?php esc_html_e('Oggi', 'commerciale-ai'); ?></small><h4><?php esc_html_e('Opportunità da gestire', 'commerciale-ai'); ?></h4></div><span class="mock-status"><?php esc_html_e('Operativo', 'commerciale-ai'); ?></span></div>
                            <div class="metric-row">
                                <div class="metric"><strong>12</strong><small><?php esc_html_e('Nuovi lead', 'commerciale-ai'); ?></small></div>
                                <div class="metric"><strong>5</strong><small><?php esc_html_e('Da rivedere', 'commerciale-ai'); ?></small></div>
                                <div class="metric"><strong>3</strong><small><?php esc_html_e('Follow-up', 'commerciale-ai'); ?></small></div>
                            </div>
                            <div class="lead-preview">
                                <div class="lead-preview__row lead-preview__head"><span><?php esc_html_e('Contatto', 'commerciale-ai'); ?></span><span><?php esc_html_e('Priorità', 'commerciale-ai'); ?></span><span><?php esc_html_e('Prossima azione', 'commerciale-ai'); ?></span></div>
                                <div class="lead-preview__row"><span><b>Studio Arco</b><small>Nuovo sito web</small></span><span><i class="heat heat--high"></i><?php esc_html_e('Alta', 'commerciale-ai'); ?></span><span><?php esc_html_e('Rivedi bozza', 'commerciale-ai'); ?></span></div>
                                <div class="lead-preview__row"><span><b>Nova Lab</b><small>Consulenza B2B</small></span><span><i class="heat heat--medium"></i><?php esc_html_e('Media', 'commerciale-ai'); ?></span><span><?php esc_html_e('Richiama oggi', 'commerciale-ai'); ?></span></div>
                                <div class="lead-preview__row"><span><b>Linea Verde</b><small>E-commerce</small></span><span><i class="heat"></i><?php esc_html_e('Da valutare', 'commerciale-ai'); ?></span><span><?php esc_html_e('Analizza lead', 'commerciale-ai'); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mock-caption"><?php esc_html_e('Esempio illustrativo dell’interfaccia.', 'commerciale-ai'); ?></p>
            </div>
        </div>
    </section>

    <section class="signal-strip" aria-label="<?php esc_attr_e('Principali capacità', 'commerciale-ai'); ?>">
        <div class="cai-container signal-grid"><span><?php esc_html_e('Lead dal sito', 'commerciale-ai'); ?></span><span><?php esc_html_e('Analisi AI', 'commerciale-ai'); ?></span><span><?php esc_html_e('Bozze email', 'commerciale-ai'); ?></span><span><?php esc_html_e('Risposte IMAP', 'commerciale-ai'); ?></span><span><?php esc_html_e('Pipeline e follow-up', 'commerciale-ai'); ?></span></div>
    </section>

    <section class="section section--paper" id="funzioni">
        <div class="cai-container">
            <div class="section-heading"><div><p class="eyebrow"><?php esc_html_e('Un solo flusso', 'commerciale-ai'); ?></p><h2><?php esc_html_e('La richiesta arriva. Il lavoro diventa chiaro.', 'commerciale-ai'); ?></h2></div><p><?php esc_html_e('Ogni funzione risolve un passaggio preciso, senza nascondere decisioni o promettere automazioni fuori controllo.', 'commerciale-ai'); ?></p></div>
            <div class="feature-grid feature-grid--links">
                <a class="feature-card" href="<?php echo esc_url(cai_page_url('acquisizione-lead')); ?>"><span class="feature-number">01</span><h3><?php esc_html_e('Raccogli', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Moduli web, inserimenti manuali ed email confluiscono in una inbox ordinata.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Acquisizione lead →', 'commerciale-ai'); ?></strong></a>
                <a class="feature-card" href="<?php echo esc_url(cai_page_url('qualificazione-ai')); ?>"><span class="feature-number">02</span><h3><?php esc_html_e('Comprendi', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Sintesi, intento, urgenza e priorità rendono leggibile ogni opportunità.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Qualificazione AI →', 'commerciale-ai'); ?></strong></a>
                <a class="feature-card" href="<?php echo esc_url(cai_page_url('risposte-conversazioni')); ?>"><span class="feature-number">03</span><h3><?php esc_html_e('Rispondi', 'commerciale-ai'); ?></h3><p><?php esc_html_e('L’AI prepara una bozza; il commerciale la verifica, la modifica e decide l’invio.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Risposte ed email →', 'commerciale-ai'); ?></strong></a>
                <a class="feature-card" href="<?php echo esc_url(cai_page_url('pipeline-follow-up')); ?>"><span class="feature-number">04</span><h3><?php esc_html_e('Segui', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Stato, timeline e prossima azione tengono il follow-up fuori dalla memoria.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Pipeline e follow-up →', 'commerciale-ai'); ?></strong></a>
                <a class="feature-card" href="<?php echo esc_url(cai_page_url('knowledge-base')); ?>"><span class="feature-number">05</span><h3><?php esc_html_e('Dai contesto', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Profilo, documenti e regole commerciali rendono le risposte più pertinenti.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Knowledge base →', 'commerciale-ai'); ?></strong></a>
                <a class="feature-card" href="<?php echo esc_url(cai_page_url('team-sicurezza-consumi')); ?>"><span class="feature-number">06</span><h3><?php esc_html_e('Controlla', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Ruoli, licenza e consumi AI restano leggibili per chi amministra il team.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Team e sicurezza →', 'commerciale-ai'); ?></strong></a>
            </div>
            <p class="section-action"><a class="text-link" href="<?php echo esc_url(cai_page_url('prodotto')); ?>"><?php esc_html_e('Esplora tutte le funzionalità →', 'commerciale-ai'); ?></a></p>
        </div>
    </section>

    <section class="section" id="come-funziona">
        <div class="cai-container">
            <div class="section-heading"><div><p class="eyebrow"><?php esc_html_e('Come funziona', 'commerciale-ai'); ?></p><h2><?php esc_html_e('Dal form alla conversazione, senza salti.', 'commerciale-ai'); ?></h2></div><p><?php esc_html_e('Il percorso quotidiano è semplice; la configurazione iniziale rende affidabile tutto ciò che viene dopo.', 'commerciale-ai'); ?></p></div>
            <div class="steps">
                <article class="step"><h3><?php esc_html_e('Collega e configura', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Profilo aziendale, fonti, knowledge base, regole e casella email.', 'commerciale-ai'); ?></p></article>
                <article class="step"><h3><?php esc_html_e('Analizza e prepara', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Il lead viene normalizzato e l’AI propone sintesi, priorità e risposta.', 'commerciale-ai'); ?></p></article>
                <article class="step"><h3><?php esc_html_e('Verifica e continua', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Il team decide, invia e segue risposte e prossime azioni nella timeline.', 'commerciale-ai'); ?></p></article>
            </div>
            <p class="section-action"><a class="cai-button cai-button--ghost" href="<?php echo esc_url(cai_page_url('come-funziona')); ?>"><?php esc_html_e('Vedi tutti i passaggi', 'commerciale-ai'); ?> →</a></p>
        </div>
    </section>

    <section class="section section--ink">
        <div class="cai-container human-control">
            <div><p class="eyebrow"><?php esc_html_e('AI responsabile', 'commerciale-ai'); ?></p><h2><?php esc_html_e('Più velocità non significa meno controllo.', 'commerciale-ai'); ?></h2></div>
            <div class="human-control__points"><article><strong>01</strong><h3><?php esc_html_e('Contesto dichiarato', 'commerciale-ai'); ?></h3><p><?php esc_html_e('L’AI usa profilo, documenti e regole configurati dall’azienda.', 'commerciale-ai'); ?></p></article><article><strong>02</strong><h3><?php esc_html_e('Output verificabile', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Analisi e bozze possono essere corrette prima di diventare azioni.', 'commerciale-ai'); ?></p></article><article><strong>03</strong><h3><?php esc_html_e('Limiti visibili', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Automazioni e consumi hanno soglie esplicite e controllabili.', 'commerciale-ai'); ?></p></article></div>
        </div>
    </section>

    <section class="section section--paper">
        <div class="cai-container">
            <div class="section-heading"><div><p class="eyebrow"><?php esc_html_e('Per chi è', 'commerciale-ai'); ?></p><h2><?php esc_html_e('Un metodo comune, tre modi di lavorare.', 'commerciale-ai'); ?></h2></div><p><?php esc_html_e('Scegli il percorso più vicino alla tua organizzazione e verifica quali passaggi fanno davvero la differenza.', 'commerciale-ai'); ?></p></div>
            <div class="solution-grid home-solutions"><a href="<?php echo esc_url(cai_page_url('professionisti')); ?>"><p class="content-kicker"><?php esc_html_e('Lavori in autonomia', 'commerciale-ai'); ?></p><h3><?php esc_html_e('Professionisti e microimprese', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Rispondi prima senza passare la giornata tra form, email e promemoria.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Scopri il percorso →', 'commerciale-ai'); ?></strong></a><a href="<?php echo esc_url(cai_page_url('team-commerciali')); ?>"><p class="content-kicker"><?php esc_html_e('Condividi i lead', 'commerciale-ai'); ?></p><h3><?php esc_html_e('Team commerciali', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Rendi priorità, conversazioni e prossime azioni accessibili al team giusto.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Scopri il percorso →', 'commerciale-ai'); ?></strong></a><a href="<?php echo esc_url(cai_page_url('agenzie-b2b')); ?>"><p class="content-kicker"><?php esc_html_e('Gestisci più servizi', 'commerciale-ai'); ?></p><h3><?php esc_html_e('Agenzie e servizi B2B', 'commerciale-ai'); ?></h3><p><?php esc_html_e('Qualifica richieste diverse con contesto e regole commerciali coerenti.', 'commerciale-ai'); ?></p><strong><?php esc_html_e('Scopri il percorso →', 'commerciale-ai'); ?></strong></a></div>
        </div>
    </section>

    <section class="section pricing-wrap" id="prezzi">
        <div class="cai-container">
            <div class="section-heading"><div><p class="eyebrow"><?php esc_html_e('Pacchetti', 'commerciale-ai'); ?></p><h2><?php esc_html_e('Scegli con numeri che puoi verificare.', 'commerciale-ai'); ?></h2></div><p><?php esc_html_e('Confronta utenti, lead mensili e budget AI. Il listino arriva direttamente dal sistema licenze ed è lo stesso usato da Stripe.', 'commerciale-ai'); ?></p></div>
            <?php if (shortcode_exists('commerciale_ai_pricing')) : echo do_shortcode('[commerciale_ai_pricing]'); else : ?><p><?php esc_html_e('Attiva il plugin Commerciale AI Client Area per mostrare i piani.', 'commerciale-ai'); ?></p><?php endif; ?>
            <div class="pricing-help"><p><?php esc_html_e('Non sai quale scegliere? Parti dal mese con più richieste e conta tutte le persone che devono accedere.', 'commerciale-ai'); ?></p><a class="text-link" href="<?php echo esc_url(cai_page_url('prezzi')); ?>"><?php esc_html_e('Apri la guida ai piani →', 'commerciale-ai'); ?></a></div>
        </div>
    </section>

    <section class="section section--paper">
        <div class="cai-container cta-band"><div><p class="eyebrow"><?php esc_html_e('Il prossimo passo', 'commerciale-ai'); ?></p><h2><?php esc_html_e('Non lasciare che il prossimo lead aspetti senza una risposta.', 'commerciale-ai'); ?></h2><p><?php esc_html_e('Confronta i pacchetti oppure raccontaci il tuo flusso prima di scegliere.', 'commerciale-ai'); ?></p></div><div class="cta-actions"><a class="cai-button cai-button--coral" href="<?php echo esc_url(cai_page_url('prezzi')); ?>"><?php esc_html_e('Scegli un piano', 'commerciale-ai'); ?> →</a><a class="cai-button cai-button--light" href="<?php echo esc_url(cai_page_url('contatti')); ?>"><?php esc_html_e('Contattaci', 'commerciale-ai'); ?></a></div></div>
    </section>
</main>
<?php get_footer(); ?>
