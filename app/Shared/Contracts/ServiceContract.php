<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Marker interface implemented by every Domain Service.
 * Enforced by architecture tests: all classes under app/Domains/Services/
 * must implement this interface, ensuring they are injectable via constructor
 * and never instantiated with `new` outside tests.
 */
interface ServiceContract {}
