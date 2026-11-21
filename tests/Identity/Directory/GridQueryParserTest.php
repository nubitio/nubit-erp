<?php

declare(strict_types=1);

namespace App\Tests\Identity\Directory;

use App\Identity\Directory\DirectoryQuery;
use App\Identity\Directory\GridQueryParser;
use PHPUnit\Framework\TestCase;

final class GridQueryParserTest extends TestCase
{
    public function testReadsTheBracketSyntaxTheGridSends(): void
    {
        $query = GridQueryParser::parse([
            'page' => '2',
            'itemsPerPage' => '20',
            'filter' => [
                ['username', 'contains', 'ana'],
                ['role',     '=',        '/api/roles/3'],
            ],
            'sort' => [['selector' => 'username', 'desc' => 'true']],
            'searchValue' => 'ana',
            'searchExpr' => ['username', 'email'],
        ]);

        static::assertSame(20, $query->offset());
        static::assertSame(
            [
                ['username', 'contains', 'ana'],
                ['role',     '=',        '/api/roles/3'],
            ],
            $query->leaves,
        );
        static::assertSame([['selector' => 'username', 'desc' => true]], $query->sort);
        static::assertSame('ana', $query->search);
        static::assertSame(['username', 'email'], $query->searchFields);
    }

    public function testReadsTheJsonEncodedForm(): void
    {
        $query = GridQueryParser::parse([
            'filter' => '[["username","contains","ana"],"and",["status","=","invited"]]',
            'sort' => '[{"selector":"role","desc":false}]',
        ]);

        static::assertSame(
            [
                ['username', 'contains', 'ana'],
                ['status',   '=',        'invited'],
            ],
            $query->leaves,
        );
        static::assertSame([['selector' => 'role', 'desc' => false]], $query->sort);
    }

    public function testAnUnwrappedSingleCriterionIsOneLeaf(): void
    {
        $query = GridQueryParser::parse(['filter' => ['username', '=', 'ana']]);

        static::assertSame([['username', '=', 'ana']], $query->leaves);
    }

    public function testMalformedParametersAreIgnoredNotFatal(): void
    {
        $query = GridQueryParser::parse([
            'filter' => 'not json',
            'sort' => [['selector' => '', 'desc' => true], 'garbage'],
            'page' => '-4',
            'itemsPerPage' => '100000',
        ]);

        static::assertSame([], $query->leaves);
        static::assertSame([], $query->sort);
        static::assertSame(1, $query->page);
        static::assertSame(DirectoryQuery::MAX_PER_PAGE, $query->perPage);
    }

    public function testTheStatusFilterDecidesWhichSidesAreWanted(): void
    {
        $invited = new DirectoryQuery(leaves: [['status', '=', 'invited']]);
        static::assertTrue($invited->wants('invited'));
        static::assertFalse($invited->wants('active'));

        $notInvited = new DirectoryQuery(leaves: [['status', '<>', 'invited']]);
        static::assertFalse($notInvited->wants('invited'));
        static::assertTrue($notInvited->wants('active'));

        static::assertTrue(new DirectoryQuery()->wants('invited'));
    }

    public function testTheUsersFilterNeverSeesTheStatusColumn(): void
    {
        $filters = new DirectoryQuery(
            leaves: [
                ['status',   '=',        'active'],
                ['username', 'contains', 'ana'],
            ],
            sort: [['selector' => 'username', 'desc' => false]],
            search: 'ana',
            searchFields: ['username'],
        )->userFilters();

        static::assertSame('[["username","contains","ana"]]', $filters['filter']);
        static::assertSame('[{"selector":"username","desc":false}]', $filters['sort']);
        static::assertSame('ana', $filters['searchValue']);
        static::assertSame(['username'], $filters['searchExpr']);
        static::assertSame([], new DirectoryQuery(leaves: [['status', '=', 'active']])->userFilters());
    }
}
