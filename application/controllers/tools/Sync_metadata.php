<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}


/**
 * @package   Jagger
 * @author    Middleware Team HEAnet
 * @author    Janusz Ulanowski <janusz.ulanowski@heanet.ie>
 * @copyright 2015 HEAnet Limited (http://www.heanet.ie)
 * @license   MIT http://www.opensource.org/licenses/mit-license.php
 */


/**
 * @property CI_Config $config
 * @property CI_Email $email
 * @property CI_Encrypt $encrypt
 * @property CI_Form_validation $form_validation
 * @property CI_FTP $ftp
 * @property CI_Input $input
 * @property CI_Loader $load
 * @property CI_Parser $parser
 * @property CI_Session $session
 * @property CI_Table $table
 * @property CI_URI $uri
 * @property CI_Output $output
 * @property CI_Lang $lang
 * @property Zacl $zacl
 * @property J_cache $j_cache
 * @property J_ncache $j_ncache
 * @property J_queue $j_queue
 * @property Approval $approval
 * @property Tracker $tracker
 * @property Emailsender $emailsender
 * @property Curl $curl
 * @property Show_element $show_element
 * @property Jauth $jauth
 * @property Arp_generator $arp_generator
 * @property Arpgen $arpgen
 * @property Providerdetails $providerdetails
 * @property Rrpreference $rrpreference
 * @property Jusermanage $jusermanage
 * @property Formelement $formelement
 * @property Xmlvalidator $xmlvalidator
 * @property Metadatavalidator $metadatavalidator
 * @property Metadata2import $metadata2import
 * @property Doctrine $doctrine
 * @property CI_Cache $cache
 */
class Sync_metadata extends CI_Controller
{

    protected $em;

    public function __construct() {
        parent::__construct();
        $this->em = $this->doctrine->em;
        $this->load->library('curl');
    }

    public function metadataslist($i = null) {
        $this->output->set_content_type('text/plain');
        $baseurl = base_url();
        $digestmethod = $this->config->item('signdigest');
        if ($digestmethod === null) {
            $digestmethod = 'SHA-1';
        }
        $result = array();
        /**
         * @var models\Provider[] $providers
         */
        if (empty($i)) {
            /**
             * @var models\Federation[] $federations
             */
            $federations = $this->em->getRepository("models\Federation")->findAll();
            foreach ($federations as $f) {
                $digest = $f->getDigest();
                if (empty($digest)) {
                    $digest = $digestmethod;
                }
                $result[] = array('group' => 'federation', 'name' => $f->getSysname(), 'url' => '' . $baseurl . 'metadata/federation/' . $f->getSysname() . '/metadata.xml', 'digest' => $digest);
                if ($f->getLocalExport() === true) {
                    $digestEx = $f->getDigestExport();
                    if (empty($digestEx)) {
                        $digestEx = $digestmethod;
                    }
                    $result[] = array('group' => 'federationexport', 'name' => $f->getSysname(), 'url' => '' . $baseurl . 'metadata/federationexport/' . $f->getSysname() . '/metadata.xml', 'digest' => $digestEx);
                }
            }
            $disableexternalcirclemeta = $this->config->item('disable_extcirclemeta');
            if (empty($disableexternalcirclemeta)) {
                $providers = $this->em->getRepository("models\Provider")->findAll();
            } else {
                $providers = $this->em->getRepository("models\Provider")->findBy(array('is_local' => true));
            }
        } else {
            $providers = $this->em->getRepository("models\Provider")->findBy(array('entityid' => base64url_decode($i)));
        }
        foreach ($providers as $p) {
            $digest = $p->getDigest();
            if (empty($digest)) {
                $digest = $digestmethod;
            }
            $result[] = array('group' => 'provider', 'name' => base64url_encode($p->getEntityId()), 'url' => '' . $baseurl . 'metadata/circle/' . base64url_encode($p->getEntityId()) . '/metadata.xml', 'digest' => $digest);
        }
        $out = "";
        foreach ($result as $r) {
            $out .= $r['group'] . ";" . $r['name'] . ";" . $r['url'] . ";" . $r['digest'] . PHP_EOL;
        }

        $this->output->set_output($out);
    }

    /**
     * $url - param is base64_encoded remote url where we want to get metadata from
     * $conditions is serialized array
     * keys of $conditions:
     *   'type' - what type of entitities to sync, possible values: all,idp,sp
     *   'is_active' - imported entities should be set as active or inactive, possible boolean values: true, false
     *   'is_local' - imported entities should be set as internal or external entities
     *   'overwrite' - if imported entity already exists in database and it's set as local. if true then overwrite all values,
     *        except is_active, is_local
     *   'populate' - imported entity should be fully populated  - both static metadata and all values,
     *        possible boolean values: true, false
     *   'default_static' - if true then static metadata will be used for metadata generation, if you set as false,
     *        then you must set 'populate' as true
     */
    public function semiautomatic($syncpass, $encoded_url, $encoded_federationurn, $conditions_to_set = null) {

        $featenabled = $this->config->item('featdisable');
        if (!is_cli() || (is_array($featenabled) && isset($featenabled['metasync']))) {
            return $this->output->set_status_header(403)->set_output('ERROR: Access denied'.PHP_EOL);
        }

        $protectpass = $this->config->item('syncpass');
        if (strlen($protectpass) < 10 || $protectpass !== $syncpass) {
            return $this->output->set_status_header(403)->set_output('ERROR: Access Denied - invalid token'.PHP_EOL);
        }
        $defaultConditions = array(
            'type'           => 'all',
            'is_active'      => true,
            'is_local'       => false,
            'overwrite'      => false,
            'populate'       => true,
            'default_static' => true,
            'removeexternal' => false,
            'mailreport'     => false,
            'email'          => null,
        );
        $conditionsInArray = array();
        if ($conditions_to_set !== null) {
            $conditionsInArray = unserialize(base64url_decode($conditions_to_set));
        }
        $conditions = array_merge($defaultConditions, $conditionsInArray);


        $url = base64url_decode($encoded_url);
        $federationurn = base64url_decode($encoded_federationurn);
        $tmpFeds = new models\Federations();
        $fed = $tmpFeds->getOneByUrn($federationurn);
        if ($fed === null) {
            return $this->output->set_status_header(500)->set_output('Federation not found');
        }
        log_message('debug', __METHOD__ . ' downloading metadata from ' . $url);
        $metadataBody = $this->curl->simple_get($url);
        if (empty($metadataBody)) {
            return $this->output->set_status_header(500)->set_output('could not retrieve data from');
        }
        $this->load->library(array('metadatavalidator', 'curl', 'metadata2import'));
        $time_start = microtime(true);
        $is_valid_metadata = $this->metadatavalidator->validateWithSchema($metadataBody);
        $time_end = microtime(true);
        $exectime = $time_end - $time_start;
        log_message('debug', __METHOD__ . ' time execustion of validating metadata  :: ' . $exectime . ' seconds');
        if (empty($is_valid_metadata)) {
            return $this->output->set_status_header(500)->set_output('Metadata from ' . $url . ' is not valid with Schema');
        }

        $typeOfEntities = strtoupper($conditions['type']);
        $full = false;
        if ($conditions['populate']) {
            $full = true;
        }

        $defaults = array(
            'overwritelocal' => $conditions['overwrite'],
            'active'         => $conditions['is_active'],
            'static'         => $conditions['default_static'],
            'local'          => $conditions['is_local'],
            'federationid'   => $fed->getId(),
            'removeexternal' => $conditions['removeexternal'],
            'mailreport'     => $conditions['mailreport'],
            'email'          => $conditions['email'],
            'attrreqinherit' => true,
        );
        $time_start = microtime(true);
        $this->metadata2import->import($metadataBody, $typeOfEntities, $full, $defaults);
        $this->j_ncache->cleanProvidersList(array('idp','sp'));
        $this->j_ncache->cleanFederationMembers($fed->getId());
        $time_end = microtime(true);
        $exectime = $time_end - $time_start;
        log_message('debug', __METHOD__ . ' total time execution of running import metadata  :: ' . $exectime . ' seconds');
        return $this->output->set_status_header(200)->set_output('Done');
    }

}
