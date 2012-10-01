<?php
namespace models;

use \Doctrine\Common\Collections\ArrayCollection;
/**
 * ResourceRegistry3
 * 
 * @package     RR3
 * @author      Middleware Team HEAnet 
 * @copyright   Copyright (c) 2012, HEAnet Limited (http://www.heanet.ie)
 * @license     MIT http://www.opensource.org/licenses/mit-license.php
 *  
 */

/**
 * User Class
 * 
 * @package     RR3
 * @subpackage  Models
 * @author      Janusz Ulanowski <janusz.ulanowski@heanet.ie>
 */

/**
 * User Model
 * @Entity
 * @Table(name="user")
 * @author janusz
 */
class User
{

    protected $em;

    /**
     * The User currently logged in
     */
    public static $current;

    /**
     * @Id
     * @Column(type="integer", nullable=false)
     * @GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @Column(type="string", length=128, unique=true, nullable=false)
     */
    protected $username;

    /**
     * if user federated then password in raw text will be stored NOPASSWORD
     * @Column(type="string", length=64, nullable=false)
     */
    protected $password;

    /**
     * @Column(type="string", length=40, nullable=true)
     */
    protected $salt;

    /**
     * @Column(type="string", length=255, unique=true, nullable=false)
     */
    protected $email;

    /**
     * @Column(type="string",length=255, unique=false, nullable=true)
     */
    protected $givenname;

    /**
     * @Column(type="string",length=255,unique=false, nullable=true)
     */
    protected $surname;




    /**
     * creator of all queue entries
     * @OneToMany(targetEntity="Queue",mappedBy="creator",cascade={"persist", "remove"})
     */
    protected $in_queue;

    /**
     * if local authn allowed
     * @Column(type="boolean")
     */
    protected $local;

    /**
     * if federated allowed
     * @Column(type="boolean")
     */
    protected $federated;

    /**
     * @Column(type="boolean")
     */
    protected $approved;

    /**
     * allow acces or not
     * @Column(type="boolean")
     */
    protected $enabled;

    /**
     * 
     * @Column(type="boolean")
     */
    protected $validated;


    /**
     * @ManyToMany(targetEntity="AclRole", inversedBy="members")
     * @JoinTable(name="aclrole_members" )
     */
    protected $roles;

    /**
     * @Column(type="datetime", nullable=true)
     */
    protected $lastlogin;

    /**
     * @Column(type="string",length=64,nullable=true)
     */
    protected $lastip;

    public function __construct()
    {
	log_message('debug','User model initiated');
        $this->in_queue = new \Doctrine\Common\Collections\ArrayCollection();
        $this->roles = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Encrypt the password before we store it
     *
     * @access	public
     * @param	string	$password
     * @return	void
     */
    public function setPassword($password)
    {
        $encrypted_password = self::encryptPassword($password);

        $this->password = $encrypted_password;
        return $this;
    }

    public function setRawPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function authenticateUser($user, $pass)
    {
        $encryted = self::encryptPassword($pass);
        $founduser = self::findUser($user);
        if (!$founduser)
        {
            return FALSE;
        }
        /**
         * 
         */
        print_r($founduser);
    }

    /**
     * Encrypt a Password
     *
     * @static
     * @access	public
     * @param	string	$password
     * @return	void
     */
    public function encryptPassword($password)
    {
	log_message('debug','Model User: encryptPassword('.$password.')');
    //    $CI = & get_instance();

        //$salt = $CI->config->item('encryption_key');
        $salt = $this->getSalt();
	log_message('debug','Model User: encryptPassword: got slat:'.$salt);
        $encrypted_password = sha1($password . $salt);

        return $encrypted_password;
    }

    public function setSalt()
    {
	log_message('debug','Model User: setSalt()');
	$length = 10;
	$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
	$string = '';
        for ($p = 0; $p < $length; $p++)
	{
		$string .= $characters[mt_rand(0, (strlen($characters))-1)];
	}
	$this->salt = $string;
	log_message('debug','Model User: salt:'.$this->salt);
	
	return $this;
    }

    public function setRole(AclRole $role)
    {
        $already_there = $this->getRoles()->contains($role);
        if(empty($already_there))
        {
             $this->getRoles()->add($role);
        }
    }

    /**
     * Authenticate this User by setting self::current to $this
     *
     * @return	User
     */
    public function authenticate()
    {
        self::$current = $this;
        return $this;
    }

    /**
     * Find a User account by username or email
     *
     * @static
     * @access	public
     * @param	string	$identifier
     * @return	User|FALSE
     */
    public static function findUser($identifier)
    {
        $CI = & get_instance();

        $user = $this->CI->em->createQuery("SELECT u FROM models\User u WHERE u.username = '{$identifier}' OR u.email = '{$identifier}'")
                ->getResult();

        return $user ? $user[0] : FALSE;
    }

    public static function fakeStatic()
    {
        return TRUE;
    }

    public function fake()
    {
        return $this;
    }

    // Begin generic set/get method stubs
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    public function setGivenname($givenname)
    {
        $this->givenname = $givenname;
        return $this;
    }

    public function setSurname($surname)
    {
        $this->surname = $surname;
        return $this;
    }

    public function setLocalEnabled()
    {
        $this->local = TRUE;
    }

    public function setLocalDisabled()
    {
        $this->local = FALSE;
    }

    public function setFederatedEnabled()
    {
        $this->federated = TRUE;
    }

    public function setFederatedDisabled()
    {
		$this->federated = FALSE;
		return $this;
    }

    public function setAccepted()
    {
		$this->approved = TRUE;
		return $this;
    }

    public function setRejected()
    {
        $this->approved = FALSE;
		return $this;
    }

    public function setEnabled()
    {
		$this->enabled = TRUE;
		return $this;
    }

    public function setDisabled()
    {
		$this->enabled = FALSE;
		return $this;
    }

	public function setIP($ip)
	{
		$this->lastip=$ip;
		return $this;
	}

    public function setValid()
    {
        $this->validated = TRUE;
    }

    public function setInvalid()
    {
        $this->validated = FALSE;
    }

	/**
	 * @PreUpdate
	 */
	public function updated()
	{
		$this->lastlogin = new \DateTime("now");
	}

    public function getId()
    {
        return $this->id;
    }

    public function getUsername()
    {
        return $this->username;
    }
    public function getFullname()
    {
        $fullname = $this->givenname." ".$this->surname;
        return $fullname;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getPassword()
    {
        return $this->password;
    }
	/**
	 * getBasic is used by j_auth lib to creta sessiondata
     */
	public function getBasic()
    {
       $data = array('username'=>$this->getUsername(),
					  'user_id'=>$this->getId());
       return $data;
    }
    public function getSalt()
    {
	log_message('debug','Model:User run getSalt() ');
	return $this->salt;
    }
  
    public function isEnabled()
    {
       return $this->enabled;
    }
    public function getFederated()
    {
      return $this->federated;
    }
    public function getLocal()
    {
      return $this->local;
    }

    public function getLastlogin()
    {
      return $this->lastlogin;
    }
    public function getIp()
    {
        return $this->lastip;
    }

    public function getRoles()
    {
        return $this->roles;
    }
    public function getRoleNames()
    {
        $rolename = array();
        $roles = $this->getRoles();
        foreach($roles as $r)
        {
          $rolename[]=$r->getName();
        }
        return $rolename;
        
    }

    // End method stubs
}