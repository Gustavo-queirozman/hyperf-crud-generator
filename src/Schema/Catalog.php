<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

interface Catalog
{
    public function tables(): string;
    public function columns(): string;
    public function indexes(): string;
    public function foreignKeys(): string;
}
