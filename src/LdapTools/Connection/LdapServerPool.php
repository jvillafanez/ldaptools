<?php
/**
 * This file is part of the LdapTools package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LdapTools\Connection;

use LdapTools\Exception\LdapConnectionException;
use LdapTools\DomainConfiguration;
use LdapTools\Utilities\LdapUtilities;
use LdapTools\Utilities\TcpSocket;

/**
 * Retrieves an available LDAP server from an array using the provided method.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class LdapServerPool
{
    /**
     * Randomize the server array before attempting to find a server to connect to.
     */
    const SELECT_RANDOM = 'random';

    /**
     * Attempt a connection to each server in the order they appear.
     */
    const SELECT_ORDER = 'order';

    /**
     * @var string The method to use when ordering the servers array.
     */
    protected $selectionMethod = self::SELECT_ORDER;

    /**
     * @var DomainConfiguration
     */
    protected $config;

    /**
     * @var TcpSocket
     */
    protected $tcp;

    /**
     * @var LdapUtilities
     */
    protected $utilities;

    /**
     * @param DomainConfiguration $config
     * @param TcpSocket|null $tcp
     * @param LdapUtilities|null $utilities
     */
    public function __construct(DomainConfiguration $config, TcpSocket $tcp = null, LdapUtilities $utilities = null)
    {
        $this->config = $config;
        $this->tcp = $tcp ?: new TcpSocket($config->getPort());
        $this->utilities = $utilities ?: new LdapUtilities();
    }

    /**
     * Retrieve the first available LDAP server.
     *
     * @return string
     * @throws LdapConnectionException
     */
    public function getServer()
    {
        $servers = $this->getSortedServersArray();

        foreach ($servers as $server) {
            if ($this->isServerAvailable($server)) {
                return $server;
            }
        }

        throw new LdapConnectionException('No LDAP server is available.');
    }

    /**
     * Set the selection method for checking servers (ie. 'random', 'order').
     *
     * @param string $method
     */
    public function setSelectionMethod($method)
    {
        if (!defined('self::SELECT_'.strtoupper($method))) {
            throw new \InvalidArgumentException(sprintf('Selection method "%s" is unknown.', $method));
        }

        $this->selectionMethod = strtolower($method);
    }

    /**
     * Get the selection method for checking servers.
     *
     * @return string
     */
    public function getSelectionMethod()
    {
        return $this->selectionMethod;
    }

    /**
     * Uses the selected method to decide how to return the server array for the check.
     *
     * @return array
     */
    public function getSortedServersArray()
    {
        $servers = empty($this->config->getServers()) ? $this->getServersFromDns() : $this->config->getServers();

        if (self::SELECT_RANDOM == $this->selectionMethod) {
            $servers = $this->shuffleServers($servers);
        }

        return $servers;
    }

    /**
     * Check if a LDAP server is up and available.
     *
     * @param string $server
     * @return bool
     */
    protected function isServerAvailable($server)
    {
        $result = $this->tcp->connect($server);
        if ($result) {
            $this->tcp->close();
        }

        return $result;
    }

    /**
     * Returns a randomized array of the servers.
     *
     * @param array $servers
     * @return array
     */
    protected function shuffleServers(array $servers)
    {
        shuffle($servers);

        return $servers;
    }

    /**
     * Attempt to lookup the LDAP servers from the DNS name.
     *
     * @return array The LDAP servers.
     * @throws LdapConnectionException
     */
    protected function getServersFromDns()
    {
        $servers = $this->utilities->getLdapServersForDomain($this->config->getDomainName());

        if (empty($servers)) {
            throw new LdapConnectionException(sprintf(
                'No LDAP servers found via DNS for "%s".',
                $this->config->getDomainName()
            ));
        }

        return $servers;
    }
}
