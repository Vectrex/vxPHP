<?php

namespace vxPHP\Session;

class JwtSessionConfig
{
    protected $serverName;

    protected $sessionContext = 'default';
    protected $timeoutMinutes = 20;
    protected $cookieDomain = null;
    protected $cookiePath = '/';
    protected $jwtKey = null;
    protected $replaceSessionHandler = null;

    /**
     * SessionConfig constructor.
     * @param $serverName
     */
    public function __construct(string $serverName)
    {
        $this->serverName = $serverName;
    }

    public function withSessionContext($context): self
    {
        $this->sessionContext = $context;
        return $this;
    }

    public function withTimeoutMinutes(int $timeout): self
    {
        $this->timeoutMinutes = $timeout;
        return $this;
    }

    public function withTimeoutHours(string $timeout): self
    {
        $this->timeoutMinutes = $timeout * 60;
        return $this;
    }

    public function withCookie(string $domain, string $path = "/"): self
    {
        $this->cookieDomain = $domain;
        $this->cookiePath = $path;
        return $this;
    }

    public function withSecret (string $secret): self
    {
        $this->jwtKey = new JwtKeySecret ($secret);
        return $this;
    }

    public function withRsaSecret(string $private, string $public): self
    {
        $this->jwtKey = new JwtRsaKey ($private, $public);
        return $this;
    }

    public function replaceSessionHandler(bool $startSession = true): self
    {
        $this->replaceSessionHandler = $startSession;
        return $this;
    }

    /**
     * @return string
     */
    public function getServerName(): string
    {
        return $this->serverName;
    }

    public function getSessionContext(): string
    {
        return $this->sessionContext;
    }

    public function getTimeoutMinutes(): int
    {
        return $this->timeoutMinutes;
    }

    public function getCookieDomain(): ?string
    {
        return $this->cookieDomain;
    }

    public function getCookiePath(): string
    {
        return $this->cookiePath;
    }

    public function getKey(): JwtKeyInterface
    {
        return $this->jwtKey;
    }

    public function isReplaceSession(): bool
    {
        return $this->replaceSessionHandler !== null;
    }

    public function isStartSession(): bool
    {
        return $this->replaceSessionHandler === true;
    }
}