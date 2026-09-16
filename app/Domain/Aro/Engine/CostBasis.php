<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

enum CostBasis: string
{
    case PartyParty = 'party_party';
    case AdvocateClient = 'advocate_client';
}
