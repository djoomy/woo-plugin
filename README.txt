=== Gateway Djomy ===
Contributors: djomydev
Donate link: https://djomy.africa/
Tags: woocommerce, payment, djomy, gateway, checkout, mobile-money
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Passerelle de paiement Djomy pour WooCommerce (version mobile money & paiements locaux). Simple, rapide et sécurisée.

== Description ==

WooCommerce Gateway Djomy permet d’ajouter la méthode de paiement Djomy directement dans WooCommerce.  
Le plugin gère la redirection de paiement, les retours de statut, un mode sandbox, et l’affichage d’une page dédiée aux transactions dans l’administration WordPress.

Fonctionnalités principales :

* Méthode de paiement Djomy intégrée dans WooCommerce  
* Mode Test / Sandbox  
* Support des callbacks et de la mise à jour du statut des commandes  
* Page Transactions Djomy dans l’admin (WP_List_Table)  
* Scripts front-end dédiés pour la redirection  
* Autoload PSR-4 via Composer  
* Code organisé et extensible

== Installation ==

= Utilisation depuis le tableau de bord WordPress =

Allez dans « Ajouter » dans le menu des extensions
Recherchez « wc-gateway-djomy »
Cliquez sur « Installer maintenant »
Activez l’extension depuis le tableau de bord des extensions

= Téléversement dans le tableau de bord WordPress =

Allez dans « Ajouter » dans le menu des extensions
Cliquez sur la zone « Téléverser »
Sélectionnez le fichier wc-gateway-djomy.zip sur votre ordinateur
Cliquez sur « Installer maintenant »

== Configuration de l'extension
1. Accédez à :  
   **WooCommerce → Réglages → Paiements → Djomy**
2. Configurez vos identifiants API, le mode Sandbox, et les URL de redirection

== FAQ ==

= Où trouver mes identifiants API Djomy ? =
Ils sont disponibles dans votre tableau de bord Djomy ou fournis par l'équipe technique.

= Est-ce que le mode sandbox fonctionne comme la production ? =
Oui, mais aucun paiement réel n’est effectué.

= Le plugin ajoute-t-il des tables ? =
Oui, une table pour stocker les transactions Djomy est créée automatiquement.

== Screenshots ==

1. Paramètres du module dans WooCommerce. admin-1.png
2. Écran de la liste des transactions Djomy dans l'administration. transactions.png

== Changelog ==

= 1.0.0 =
* Première version stable.
* Ajout de la passerelle WooCommerce.
* Page d'administration Transactions Djomy.
* Support du mode sandbox.

== Avis de mise à jour ==

= 1.0.0 =
Version stable initiale du module Djomy pour WooCommerce.

== Un bref exemple de Markdown ==

Ordered list:

1. Intégration rapide.
2. Paiement sécurisé.
3. Compatible WooCommerce 5+ et 6+.

Unordered list:

* Mode test
* Callbacks
* WP_List_Table Admin


Blockquote:

> Djomy – La passerelle de paiement rapide et flexible.

Code example :

`<?php echo "Paiement Djomy Actif"; ?>`
