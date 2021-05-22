<?php


namespace vxPHP\Session;


interface SessionStorageInterface
{
    public function start(): bool;

    public function isStarted(): bool;

    public function getId(): ?string;

    public function setId(string $id): SessionStorageInterface;

    public function getName(): ?string;

    public function setName(string $name): SessionStorageInterface;

    public function regenerate(): bool;

    public function clear(): void;

    public function save(): void;

    public function getBag(): SessionDataBag;

    public function setBag(SessionDataBag $bag): SessionStorageInterface;

    public function setHandler($handler = null): SessionStorageInterface;
}