jQuery(document).ready(function($) {
    // Sélecteurs des champs WooCommerce admin
    const testMode = $('#woocommerce_djomy_testmode');
    const baseUrl  = $('#woocommerce_djomy_base_url');
    
    // Fonction pour mettre à jour dynamiquement l'URL
    const updateBaseUrl = () => {
        if (testMode.is(':checked')) {
            baseUrl.val(djomy_settings.sandbox_url); // Mode test
        } else {
            baseUrl.val(djomy_settings.production_url); // Mode production
        }
    }

    // Initialisation au chargement
    updateBaseUrl();

    // Changement dynamique lorsque la checkbox est cochée/décochée
    testMode.on('change', function() {
        updateBaseUrl();
    });
    

    // Ajoute un bouton à côté du champ icon_url
    const field = $('input[name="woocommerce_djomy_icon_url"]');
    if (field.length && !field.next('.button-djomy-upload').length) {
        field.after('<button type="button" class="button button-secondary button-djomy-upload">Choisir une image</button>');
    }

    let mediaUploader;
    $(document).on('click', '.button-djomy-upload', function (e) {
        e.preventDefault();

        // Si le sélecteur existe déjà, on le rouvre
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Crée une nouvelle instance du sélecteur média
        mediaUploader = wp.media({
            title: 'Choisir une icône pour Djomy',
            button: { text: 'Utiliser cette image' },
            multiple: false
        });

        // Quand une image est sélectionnée
        mediaUploader.on('select', function () {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            field.val(attachment.url);
        });

        mediaUploader.open();
    });   
});
