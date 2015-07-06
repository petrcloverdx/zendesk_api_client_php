<?php

namespace Zendesk\API;

/*
 * Dead simple autoloader:
 * spl_autoload_register(function($c){@include 'src/'.preg_replace('#\\\|_(?!.+\\\)#','/',$c).'.php';});
 */

use Zendesk\API\Exceptions\AuthException;
use Zendesk\API\Resources\Attachments;
use Zendesk\API\Resources\AuditLogs;
use Zendesk\API\Resources\Automations;
use Zendesk\API\Resources\DynamicContent;
use Zendesk\API\Resources\Groups;
use Zendesk\API\Resources\JobStatuses;
use Zendesk\API\Resources\Macros;
use Zendesk\API\Resources\OrganizationFields;
use Zendesk\API\Resources\OrganizationMemberships;
use Zendesk\API\Resources\Organizations;
use Zendesk\API\Resources\OrganizationSubscriptions;
use Zendesk\API\Resources\Search;
use Zendesk\API\Resources\Tags;
use Zendesk\API\Resources\Targets;
use Zendesk\API\Resources\Tickets;
use Zendesk\API\Resources\Triggers;
use Zendesk\API\Resources\UserFields;
use Zendesk\API\Resources\Users;
use Zendesk\API\Resources\Views;
use Zendesk\API\Utilities\Auth;
use Zendesk\API\UtilityTraits\InstantiatorTrait;

/**
 * Client class, base level access
 * @method Debug debug()
 * @method Tickets ticket()
 * @method Views views()
 * @method Users users()
 * @method Tags tags()
 * @method Macros macros()
 * @method Attachments attachemnts()
 * @method Groups groups()
 * @method Automations automations()
 * @method Triggers triggers()
 * @method Targets targets()
 * @method UserFields userFields()
 * @method AuditLogs auditLogs()
 * @method OrganizationFields organizationFields()
 * @method DynamicContent dynamicContent()
 * @method Organizations organizations()
 */
class HttpClient
{
    use InstantiatorTrait;

    /**
     * @var Auth
     */
    protected $auth;
    /**
     * @var string
     */
    protected $authStrategy;
    /**
     * @var Array
     */
    protected $authOptions;
    /**
     * @var string
     */
    protected $subdomain;
    /**
     * @var string
     */
    protected $username;
    /**
     * @var string
     */
    protected $scheme;
    /**
     * @var string
     */
    protected $hostname;
    /**
     * @var integer
     */
    protected $port;
    /**
     * @var string
     */
    protected $token;
    /**
     * @var string
     */
    protected $oAuthToken;
    /**
     * @var string
     */
    protected $apiUrl;
    /**
     * @var string
     */
    protected $apiVer = 'v2';
    /**
     * @var array|null
     */
    protected $sideload;

    /**
     * @var Debug
     */
    protected $debug;
    /**
     * @var \GuzzleHttp\Client
     */
    public $guzzle;

    /**
     * @param string $subdomain
     * @param string $username
     */

    public function __construct(
        $subdomain,
        $username,
        $scheme = "https",
        $hostname = "zendesk.com",
        $port = 443,
        $guzzle = null
    ) {
        if (is_null($guzzle)) {
            $this->guzzle = new \GuzzleHttp\Client();
        } else {
            $this->guzzle = $guzzle;
        }

        $this->subdomain = $subdomain;
        $this->username  = $username;
        $this->hostname  = $hostname;
        $this->scheme    = $scheme;
        $this->port      = $port;

        if (empty($subdomain)) {
            $this->apiUrl = "$scheme://$hostname:$port/api/{$this->apiVer}/";
        } else {
            $this->apiUrl = "$scheme://$subdomain.$hostname:$port/api/{$this->apiVer}/";
        }

        $this->debug = new Debug();
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public static function getValidSubResources()
    {
        return [
            'attachments'               => Attachments::class,
            'auditLogs'                 => AuditLogs::class,
            'automations'               => Automations::class,
            'dynamicContent'            => DynamicContent::class,
            'groups'                    => Groups::class,
            'jobStatuses'               => JobStatuses::class,
            'macros'                    => Macros::class,
            'organizationFields'        => OrganizationFields::class,
            'organizationMemberships'   => OrganizationMemberships::class,
            'organizations'             => Organizations::class,
            'organizationSubscriptions' => OrganizationSubscriptions::class,
            'search'                    => Search::class,
            'tags'                      => Tags::class,
            'targets'                   => Targets::class,
            'tickets'                   => Tickets::class,
            'triggers'                  => Triggers::class,
            'userFields'                => UserFields::class,
            'users'                     => Users::class,
            'views'                     => Views::class,
        ];
    }

    /**
     * Configure the authorization method
     *
     * @param       $strategy
     * @param array $options
     *
     * @throws AuthException
     */
    public function setAuth($strategy, array $options)
    {
        $validAuthStrategies = [Auth::BASIC, Auth::OAUTH];
        if (! in_array($strategy, $validAuthStrategies)) {
            throw new AuthException(
                'Invalid auth strategy set, please use `'
                . implode('` or `', $validAuthStrategies)
                . '`'
            );
        }

        $this->authStrategy = $strategy;

        if ($strategy == Auth::BASIC) {
            if (! array_key_exists('username', $options) || ! array_key_exists('token', $options)) {
                throw new AuthException('Please supply `username` and `token` for basic auth.');
            }
        } elseif ($strategy == Auth::OAUTH) {
            if (! array_key_exists('token', $options)) {
                throw new AuthException('Please supply `token` for oauth.');
            }
        }

        $this->authOptions = $options;
    }

    /**
     * Returns the supplied subdomain
     *
     * @return string
     */
    public function getSubdomain()
    {
        return $this->subdomain;
    }

    /**
     * Returns the generated api URL
     *
     * @return string
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * Returns a text value indicating the type of authorization configured
     *
     * @return string
     */
    public function getAuthOptions()
    {
        return $this->authOptions;
    }

    /**
     * Returns the authentication strategy set
     *
     * @return string
     */
    public function getAuthStrategy()
    {
        return $this->authStrategy;
    }

    /**
     * Set debug information as an object
     *
     * @param mixed  $lastRequestHeaders
     * @param mixed  $lastResponseCode
     * @param string $lastResponseHeaders
     * @param mixed  $lastResponseError
     */
    public function setDebug(
        $lastRequestHeaders,
        $lastRequestBody,
        $lastResponseCode,
        $lastResponseHeaders,
        $lastResponseError
    ) {
        $this->debug->lastRequestHeaders  = $lastRequestHeaders;
        $this->debug->lastRequestBody     = $lastRequestBody;
        $this->debug->lastResponseCode    = $lastResponseCode;
        $this->debug->lastResponseHeaders = $lastResponseHeaders;
        $this->debug->lastResponseError   = $lastResponseError;
    }

    /**
     * Returns debug information in an object
     *
     * @return Debug
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * Sideload setter
     *
     * @param array|null $fields
     *
     * @return HttpClient
     */
    public function setSideload(array $fields = null)
    {
        $this->sideload = $fields;

        return $this;
    }

    /**
     * Sideload getter
     *
     * @param array|null $params
     *
     * @return array|null
     */
    public function getSideload(array $params = null)
    {
        if ((isset($params['sideload'])) && (is_array($params['sideload']))) {
            return $params['sideload'];
        } else {
            return $this->sideload;
        }
    }

    /*
     * These ones don't follow the usual construct
     */

    /**
     * @param int $id
     *
     * @return $this
     */
    public function category($id)
    {
        return $this->categories->setLastId($id);
    }

    /**
     * @param int|null $id
     *
     * @return ActivityStream
     */
    public function activities($id = null)
    {
        return ($id != null ? $this->activityStream()->setLastId($id) : $this->activityStream());
    }

    /**
     * @param int $id
     *
     * @return ActivityStream
     */
    public function activity($id)
    {
        return $this->activityStream()->setLastId($id);
    }

    public function get($endpoint, $queryParams = [])
    {
        $sideloads = $this->getSideload($queryParams);

        // TODO: filter allowed query params
        if (is_array($sideloads)) {
            $queryParams['include'] = implode(',', $sideloads);
            unset($queryParams['sideload']);
        }

        $response = Http::send(
            $this,
            $endpoint,
            ['queryParams' => $queryParams]
        );

        $this->setSideload(null);

        return $response;
    }

    /**
     * This is a helper method to do a post request.
     *
     * @param       $endpoint
     * @param array $postData
     *
     * @return array
     * @throws Exceptions\ApiResponseException
     */
    public function post($endpoint, $postData = [])
    {
        $response = Http::send(
            $this,
            $endpoint,
            [
                'postFields' => $postData,
                'method'     => 'POST'
            ]
        );

        $this->setSideload(null);

        return $response;
    }

    /**
     * This is a helper method to do a put request.
     *
     * @param       $endpoint
     * @param array $putData
     *
     * @return array
     * @throws Exceptions\ApiResponseException
     */
    public function put($endpoint, $putData = [])
    {
        $response = Http::send(
            $this,
            $endpoint,
            ['postFields' => $putData, 'method' => 'PUT']
        );

        $this->setSideload(null);

        return $response;
    }

    /**
     * This is a helper method to do a delete request.
     *
     * @param $endpoint
     *
     * @return array
     * @throws Exceptions\ApiResponseException
     */
    public function delete($endpoint)
    {
        $response = Http::send(
            $this,
            $endpoint,
            ['method' => 'DELETE']
        );

        $this->setSideload(null);

        return $response;
    }
}
