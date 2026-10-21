<?php
/**
 * Module Descriptor for KYB Dolibarr Connector
 *
 * @package   KYB Dolibarr
 * @author    Mouadh Benhassine (Digidev Agency)
 * @copyright 2026 Digidev Agency
 * @license   GPL-3.0-or-later
 */

// Load Dolibarr environment
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modKybDolibarr
 * Descriptor for the KYB Sovereign Algerian Corporate Identity & Fiscal Verification module.
 */
class modKybDolibarr extends DolibarrModules
{
    /**
     * Constructor. Defines all module properties, dependencies, and hooks.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Id for module (must be unique within the Dolibarr ecosystem)
        $this->numero = 500120;

        // Key text used to identify module (for example, for rights, names of parameters...)
        $this->name = 'kybdolibarr';
        $this->family = 'financial';
        $this->module_position = '50';

        // Module label and description
        $this->description = "Authentification légale NIF/RC (<10ms), conformité temporelle CIDTA 169/176 & CTCA 30 et autocomplétion des tiers algériens";

        // Publisher information
        $this->editor_name = 'Digidev Agency (Mouadh Benhassine)';
        $this->editor_url = 'https://kyb.digidevagency.dz';

        // Version of module
        $this->version = '1.0.0';

        // Constant name for activation tracking
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Icon for the module
        $this->picto = 'kyb_icon@kybdolibarr';

        // Parts of Dolibarr to hook into
        $this->module_parts = array(
            'hooks' => array(
                'thirdpartycard',
                'thirdpartylist',
                'invoicecard',
                'invoicesuppliercard',
                'propalcard',
                'ordersuppliercard',
                'formObjectOptions',
                'doActions',
                'addMoreMassActions',
                'doMassActions'
            )
        );

        // Data directories to create when module is enabled
        $this->dirs = array('/kybdolibarr/temp');

        // Config page url
        $this->config_page_url = array("setup.php@kybdolibarr");

        // Dependencies
        $this->hidden = false;
        $this->depends = array("modSociete", "modFacture");
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("kybdolibarr@kybdolibarr");

        // PHP and Dolibarr version compatibility (19.0 through 22.x)
        $this->phpmin = array(8, 0);
        $this->need_dolibarr_version = array(19, 0);
        $this->max_dolibarr_version = array(22, 99);

        // Constants initialized on module activation
        $this->const = array(
            1 => array(
                'KYB_API_URL',
                'chaine',
                'https://kyb.digidevagency.dz',
                'URL de l\'API de vérification KYB Algérie',
                0
            ),
            2 => array(
                'KYB_API_KEY',
                'chaine',
                '',
                'Clé API Secrète KYB (sk_live_...)',
                0
            ),
            3 => array(
                'KYB_TIMEOUT',
                'chaine',
                '5',
                'Délai d\'expiration réseau en secondes',
                0
            ),
            4 => array(
                'KYB_STRICT_BLOCKING',
                'chaine',
                '0',
                'Bloquer la validation si fournisseur radié (1: Bloquer, 0: Avertir)',
                0
            ),
            5 => array(
                'KYB_SANDBOX_MODE',
                'chaine',
                '0',
                'Activer le mode bac à sable avec clés déterministes 0000',
                0
            ),
            6 => array(
                'MAIN_DISABLEPROFIDRULES',
                'chaine',
                '1',
                'Désactive la vérification erronée du NIF sur le champ RC en Algérie',
                0
            )
        );

        // Permissions provided by the module
        $this->rights = array();
        $this->rights_class = 'kybdolibarr';

        $r = 0;
        $this->rights[$r][0] = $this->numero + 1;
        $this->rights[$r][1] = 'Consulter les audits fiscaux et fiches KYB des tiers';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';

        $r++;
        $this->rights[$r][0] = $this->numero + 2;
        $this->rights[$r][1] = 'Configurer les paramètres de connexion à l\'API KYB';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'config';
    }

    /**
     * Function called when module is enabled.
     *
     * @param string $options Options when enabling module
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        $sql = array();
        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled.
     *
     * @param string $options Options when disabling module
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
