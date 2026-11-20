<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use Nubit\Platform\Report\GridFilterApplier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ReportGridFilterApplierTest extends TestCase
{
    private GridFilterApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new GridFilterApplier();
    }

    // -------------------------------------------------------------------------
    // buildSql — empty / invalid input
    // -------------------------------------------------------------------------

    public function test_empty_filter_returns_empty_string(): void
    {
        $params = ['local_id' => '1'];
        $result = $this->applier->buildSql('', ['issuedDate' => 's.issued_date'], $params);

        self::assertSame('', $result);
        self::assertArrayNotHasKey('grid_filter_0', $params);
    }

    public function test_whitespace_only_filter_returns_empty_string(): void
    {
        $params = [];
        self::assertSame('', $this->applier->buildSql('   ', [], $params));
    }

    public function test_invalid_json_throws_bad_request(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $params = [];
        $this->applier->buildSql('{not-valid', ['field' => 'col'], $params);
    }

    public function test_non_array_json_returns_empty_string(): void
    {
        $params = [];
        self::assertSame('', $this->applier->buildSql('"just a string"', [], $params));
    }

    // -------------------------------------------------------------------------
    // buildSql — field allowlist
    // -------------------------------------------------------------------------

    public function test_unknown_field_is_ignored(): void
    {
        $params = [];
        $filter = json_encode(['unknownField', '=', 'value'], JSON_THROW_ON_ERROR);
        $result = $this->applier->buildSql($filter, ['issuedDate' => 's.issued_date'], $params);

        self::assertSame('', $result);
        self::assertEmpty($params);
    }

    public function test_known_field_produces_condition(): void
    {
        $params = [];
        $filter = json_encode(['issuedDate', '=', '2024-01-15'], JSON_THROW_ON_ERROR);
        $result = $this->applier->buildSql($filter, ['issuedDate' => 's.issued_date'], $params);

        self::assertSame('AND s.issued_date = :grid_filter_0', $result);
        self::assertSame('2024-01-15', $params['grid_filter_0']);
    }

    // -------------------------------------------------------------------------
    // buildSql — operator mapping
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{string, mixed, string, mixed}>
     */
    public static function operatorProvider(): array
    {
        return [
            'equals' => ['=', 'val', '=', 'val'],
            'not equals' => ['<>', 'val', '<>', 'val'],
            'greater' => ['>', '5', '>', '5'],
            'gte' => ['>=', '5', '>=', '5'],
            'less' => ['<', '5', '<', '5'],
            'lte' => ['<=', '5', '<=', '5'],
            'contains' => ['contains', 'foo', 'LIKE', '%foo%'],
            'notcontains' => ['notcontains', 'foo', 'NOT LIKE', '%foo%'],
            'startswith' => ['startswith', 'foo', 'LIKE', 'foo%'],
            'endswith' => ['endswith', 'foo', 'LIKE', '%foo'],
        ];
    }

    #[DataProvider('operatorProvider')]
    public function test_operator_mapping(
        string $frontendOp,
        mixed $inputValue,
        string $expectedSqlOp,
        mixed $expectedParamValue,
    ): void {
        $params = [];
        $filter = json_encode(['myField', $frontendOp, $inputValue], JSON_THROW_ON_ERROR);
        $result = $this->applier->buildSql($filter, ['myField' => 'col'], $params);

        self::assertStringContainsString("col {$expectedSqlOp} :grid_filter_0", $result);
        self::assertSame($expectedParamValue, $params['grid_filter_0']);
    }

    public function test_unknown_operator_is_ignored(): void
    {
        $params = [];
        $filter = json_encode(['myField', 'invalidOp', 'value'], JSON_THROW_ON_ERROR);
        $result = $this->applier->buildSql($filter, ['myField' => 'col'], $params);

        self::assertSame('', $result);
        self::assertEmpty($params);
    }

    // -------------------------------------------------------------------------
    // buildSql — multiple conditions / nested AST
    // -------------------------------------------------------------------------

    public function test_multiple_conditions_joined_with_and(): void
    {
        $params = [];
        $filter = json_encode([
            ['issuedDate', '>=', '2024-01-01'],
            'and',
            ['issuedDate', '<=', '2024-01-31'],
        ], JSON_THROW_ON_ERROR);
        $fieldMap = ['issuedDate' => 's.issued_date'];
        $result = $this->applier->buildSql($filter, $fieldMap, $params);

        self::assertStringStartsWith('AND ', $result);
        self::assertStringContainsString('s.issued_date >= :grid_filter_0', $result);
        self::assertStringContainsString('s.issued_date <= :grid_filter_1', $result);
        self::assertSame('2024-01-01', $params['grid_filter_0']);
        self::assertSame('2024-01-31', $params['grid_filter_1']);
    }

    public function test_nested_ast_is_flattened(): void
    {
        $params = [];
        // DevExtreme nesting: [[field1, op, val1], 'and', [field2, op, val2]]
        $filter = json_encode([
            [['fieldA', '=', 'alpha'], 'and', ['fieldB', '=', 'beta']],
        ], JSON_THROW_ON_ERROR);
        $fieldMap = ['fieldA' => 'col_a', 'fieldB' => 'col_b'];
        $result = $this->applier->buildSql($filter, $fieldMap, $params);

        self::assertStringContainsString('col_a = :grid_filter_0', $result);
        self::assertStringContainsString('col_b = :grid_filter_1', $result);
    }

    // -------------------------------------------------------------------------
    // buildSql — param index offset (pre-existing params)
    // -------------------------------------------------------------------------

    public function test_param_index_starts_after_existing_params(): void
    {
        $params = ['local_id' => '1', 'date_from' => '2024-01-01'];
        $filter = json_encode(['myField', '=', 'hello'], JSON_THROW_ON_ERROR);
        $result = $this->applier->buildSql($filter, ['myField' => 'col'], $params);

        // index starts at count($params) = 2
        self::assertSame('AND col = :grid_filter_2', $result);
        self::assertSame('hello', $params['grid_filter_2']);
    }

    // -------------------------------------------------------------------------
    // buildSql — relation identifier unwrapping
    // -------------------------------------------------------------------------

    public function test_relation_identifier_array_unwraps_id(): void
    {
        $params = [];
        $filter = json_encode(['customer', '=', ['id' => 'uuid-123', 'label' => 'ACME']], JSON_THROW_ON_ERROR);
        $result = $this->applier->buildSql($filter, ['customer' => 'c.id'], $params);

        self::assertSame('AND c.id = :grid_filter_0', $result);
        self::assertSame('uuid-123', $params['grid_filter_0']);
    }

    // -------------------------------------------------------------------------
    // flattenRules
    // -------------------------------------------------------------------------

    public function test_flat_leaf_rule_returned_as_is(): void
    {
        $rules = $this->applier->flattenRules(['field', '=', 'val']);
        self::assertSame([['field', '=', 'val']], $rules);
    }

    public function test_nested_rules_are_flattened(): void
    {
        $rules = $this->applier->flattenRules([
            ['a', '=', '1'],
            'and',
            ['b', '=', '2'],
        ]);
        self::assertCount(2, $rules);
        self::assertSame(['a', '=', '1'], $rules[0]);
        self::assertSame(['b', '=', '2'], $rules[1]);
    }

    // -------------------------------------------------------------------------
    // normalizeValue
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{string, mixed, mixed}>
     */
    public static function normalizeValueProvider(): array
    {
        return [
            'contains wraps' => ['contains', 'foo', '%foo%'],
            'notcontains wraps' => ['notcontains', 'bar', '%bar%'],
            'startswith appends' => ['startswith', 'baz', 'baz%'],
            'endswith prepends' => ['endswith', 'qux', '%qux'],
            'equals passthrough' => ['=', 'hello', 'hello'],
            'null passthrough' => ['=', null, null],
            'int passthrough' => ['=', 42, 42],
        ];
    }

    #[DataProvider('normalizeValueProvider')]
    public function test_normalize_value(string $operator, mixed $input, mixed $expected): void
    {
        self::assertSame($expected, $this->applier->normalizeValue($operator, $input));
    }
}
