    # 🧾 Gateway Djomy

    **Gateway Djomy** est une passerelle de paiement WooCommerce
    permettant d'intégrer facilement le service **Djomy** aux sites
    WordPress.\
    Le plugin ajoute une méthode de paiement dédiée, gère les transactions,
    la redirection de paiement ainsi que l'affichage des transactions dans
    l'admin WordPress.

    ## 🚀 Fonctionnalités

    -   Intégration complète de Djomy comme mode de paiement WooCommerce\
    -   Mode **Test / Sandbox** disponible\
    -   Page d'administration dédiée pour consulter les transactions\
    -   Webhooks / callbacks pour mise à jour des statuts\
    -   Scripts JS dédiés\
    -   Autoload PSR-4 via Composer\
    -   Code structuré (`includes/`, `assets/`, `vendor/`)

    ## 📂 Structure du Plugin

        wc-gateway-djomy/
        │
        ├── wc-gateway-djomy.php        # Fichier principal du plugin
        ├── autoload.php                # Autoload personnalisé
        ├── composer.json               # Chargement PSR-4
        │
        ├── includes/
        │   ├── Gateway.php             # Classe principale de la passerelle
        │   ├── DB.php                  # Gestion base de données
        │   └── TransactionsList.php    # Liste admin (WP_List_Table)
        │
        ├── assets/
        │   └── js/
        │       └── djomy.js            # Script JS pour la redirection + AJAX
        │
        └── vendor/                     # Autoload Composer

    ## 📦 Installation

    1.  Télécharger le dossier du plugin\
    2.  Le placer dans :

    ```{=html}
    <!-- -->
    ```
        wp-content/plugins/wc-gateway-djomy

    3.  Activer le plugin depuis **Extensions \> Installées**\
    4.  Aller dans :\
        **WooCommerce → Réglages → Paiements → Djomy**

    ## ⚙️ Configuration

    -   Activer/Désactiver Djomy\
    -   Test Mode (sandbox)\
    -   Identifiants API Djomy\
    -   URL sandbox & production\
    -   Méthode de redirection\
    -   Page de retour après paiement

    ## 📝 Scripts Front-End

    ``` php
    public function enqueue_scripts() {
        wp_enqueue_script(
            'djomy-js',
            plugins_url('/assets/js/djomy.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('djomy-js', 'djomy_settings', [
            'testmode'    => $this->get_option('testmode'),
            'sandbox_url' => 'https://sandbox...',
        ]);
    }
    ```

    ## 🔐 Sécurité

    -   Vérification nonce sur les actions sensibles\
    -   Filtrage des données entrantes\
    -   Sécurisation des callbacks\
    -   Utilisation du système natif WooCommerce (`wc_add_notice`, hooks,
        status...)

    ## 🧰 Transactions

    Une page **Transactions Djomy** est créée dans l'admin WordPress pour
    consulter :

    -   ID transaction\
    -   Montant\
    -   Statut\
    -   Client\
    -   Date\
    -   Logs techniques

    ## 👨‍💻 Développement

    ### Autoload PSR-4

    ``` json
    {
    "autoload": {
        "psr-4": {
        "Djomy\": "includes/"
        }
    }
    }
    ```

    ### Recompiler l'autoload

        composer dump-autoload

    ## 🧪 Mode Sandbox

    Toutes les transactions passent par sandbox :

        https://sandbox-api.djomy.africa

    ## 📄 Licence

    Ce plugin est distribué sous licence MIT.

    ## 🤝 Contribuer

    Les PR sont les bienvenues !