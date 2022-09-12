<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/_profiler' => [[['_route' => '_profiler_home', '_controller' => 'web_profiler.controller.profiler::homeAction'], null, null, null, true, false, null]],
        '/_profiler/search' => [[['_route' => '_profiler_search', '_controller' => 'web_profiler.controller.profiler::searchAction'], null, null, null, false, false, null]],
        '/_profiler/search_bar' => [[['_route' => '_profiler_search_bar', '_controller' => 'web_profiler.controller.profiler::searchBarAction'], null, null, null, false, false, null]],
        '/_profiler/phpinfo' => [[['_route' => '_profiler_phpinfo', '_controller' => 'web_profiler.controller.profiler::phpinfoAction'], null, null, null, false, false, null]],
        '/_profiler/xdebug' => [[['_route' => '_profiler_xdebug', '_controller' => 'web_profiler.controller.profiler::xdebugAction'], null, null, null, false, false, null]],
        '/_profiler/open' => [[['_route' => '_profiler_open_file', '_controller' => 'web_profiler.controller.profiler::openAction'], null, null, null, false, false, null]],
        '/api/category' => [[['_route' => 'api_category_get', '_controller' => 'App\\Api\\CategoryController::index'], null, ['GET' => 0], null, false, false, null]],
        '/api/rol' => [[['_route' => 'api_rol_get', '_controller' => 'App\\Api\\RolController::index'], null, ['GET' => 0], null, false, false, null]],
        '/category' => [[['_route' => 'category_index', '_controller' => 'App\\Category\\Application\\CategoryController::index'], null, ['GET' => 0], null, false, false, null]],
        '/category/get' => [[['_route' => 'category_list', '_controller' => 'App\\Category\\Application\\CategoryController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/category/add' => [[['_route' => 'category_create', '_controller' => 'App\\Category\\Application\\CategoryController::add'], null, ['POST' => 0], null, false, false, null]],
        '/company' => [[['_route' => 'company_index', '_controller' => 'App\\Company\\Application\\CompanyController::index'], null, ['GET' => 0], null, false, false, null]],
        '/company/get' => [[['_route' => 'company_list', '_controller' => 'App\\Company\\Application\\CompanyController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/company/add' => [[['_route' => 'company_create', '_controller' => 'App\\Company\\Application\\CompanyController::add'], null, ['POST' => 0], null, false, false, null]],
        '/local' => [[['_route' => 'local_index', '_controller' => 'App\\Local\\Application\\LocalController::index'], null, ['GET' => 0], null, false, false, null]],
        '/local/get' => [[['_route' => 'local_list', '_controller' => 'App\\Local\\Application\\LocalController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/local/add' => [[['_route' => 'local_create', '_controller' => 'App\\Local\\Application\\LocalController::add'], null, ['POST' => 0], null, false, false, null]],
        '/product' => [[['_route' => 'product_index', '_controller' => 'App\\Product\\Application\\ProductController::index'], null, ['GET' => 0], null, false, false, null]],
        '/product/get' => [[['_route' => 'product_list', '_controller' => 'App\\Product\\Application\\ProductController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/product/add' => [[['_route' => 'product_create', '_controller' => 'App\\Product\\Application\\ProductController::add'], null, ['POST' => 0], null, false, false, null]],
        '/sale' => [[['_route' => 'sale_index', '_controller' => 'App\\Sale\\Application\\SaleController::index'], null, ['GET' => 0], null, false, false, null]],
        '/sale/get' => [[['_route' => 'sale_list', '_controller' => 'App\\Sale\\Application\\SaleController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/sale/add' => [[['_route' => 'sale_create', '_controller' => 'App\\Sale\\Application\\SaleController::add'], null, ['POST' => 0], null, false, false, null]],
        '/saledetail' => [[['_route' => 'saledetail_index', '_controller' => 'App\\SaleDetail\\Application\\SaleDetailController::index'], null, ['GET' => 0], null, false, false, null]],
        '/saledetail/get' => [[['_route' => 'saledetail_list', '_controller' => 'App\\SaleDetail\\Application\\SaleDetailController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/saledetail/add' => [[['_route' => 'saledetail_create', '_controller' => 'App\\SaleDetail\\Application\\SaleDetailController::add'], null, ['POST' => 0], null, false, false, null]],
        '/supplier' => [[['_route' => 'supplier_index', '_controller' => 'App\\Supplier\\Application\\SupplierController::index'], null, ['GET' => 0], null, false, false, null]],
        '/supplier/get' => [[['_route' => 'supplier_list', '_controller' => 'App\\Supplier\\Application\\SupplierController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/supplier/add' => [[['_route' => 'supplier_create', '_controller' => 'App\\Supplier\\Application\\SupplierController::add'], null, ['POST' => 0], null, false, false, null]],
        '/transaction' => [[['_route' => 'transaction_index', '_controller' => 'App\\Transaction\\Application\\TransactionController::index'], null, ['GET' => 0], null, false, false, null]],
        '/transaction/get' => [[['_route' => 'transaction_list', '_controller' => 'App\\Transaction\\Application\\TransactionController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/transaction/add' => [[['_route' => 'transaction_create', '_controller' => 'App\\Transaction\\Application\\TransactionController::add'], null, ['POST' => 0], null, false, false, null]],
        '/unit' => [[['_route' => 'unit_index', '_controller' => 'App\\Unit\\Application\\UnitController::index'], null, ['GET' => 0], null, false, false, null]],
        '/unit/get' => [[['_route' => 'unit_list', '_controller' => 'App\\Unit\\Application\\UnitController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/unit/add' => [[['_route' => 'unit_create', '_controller' => 'App\\Unit\\Application\\UnitController::add'], null, ['POST' => 0], null, false, false, null]],
        '/user' => [[['_route' => 'user_index', '_controller' => 'App\\User\\Application\\UserController::index'], null, ['GET' => 0], null, false, false, null]],
        '/user/get' => [[['_route' => 'user_list', '_controller' => 'App\\User\\Application\\UserController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/user/add' => [[['_route' => 'user_create', '_controller' => 'App\\User\\Application\\UserController::add'], null, ['POST' => 0], null, false, false, null]],
        '/voucherseries' => [[['_route' => 'voucherseries_index', '_controller' => 'App\\VoucherSeries\\Application\\VoucherSeriesController::index'], null, ['GET' => 0], null, false, false, null]],
        '/voucherseries/get' => [[['_route' => 'voucherseries_list', '_controller' => 'App\\VoucherSeries\\Application\\VoucherSeriesController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/voucherseries/add' => [[['_route' => 'voucherseries_create', '_controller' => 'App\\VoucherSeries\\Application\\VoucherSeriesController::add'], null, ['POST' => 0], null, false, false, null]],
        '/vouchertype' => [[['_route' => 'vouchertype_index', '_controller' => 'App\\VoucherType\\Application\\VoucherTypeController::index'], null, ['GET' => 0], null, false, false, null]],
        '/vouchertype/get' => [[['_route' => 'vouchertype_list', '_controller' => 'App\\VoucherType\\Application\\VoucherTypeController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/vouchertype/add' => [[['_route' => 'vouchertype_create', '_controller' => 'App\\VoucherType\\Application\\VoucherTypeController::add'], null, ['POST' => 0], null, false, false, null]],
        '/warehouse' => [[['_route' => 'warehouse_index', '_controller' => 'App\\Warehouse\\Application\\WarehouseController::index'], null, ['GET' => 0], null, false, false, null]],
        '/warehouse/get' => [[['_route' => 'warehouse_list', '_controller' => 'App\\Warehouse\\Application\\WarehouseController::getAll'], null, ['POST' => 0], null, false, false, null]],
        '/warehouse/add' => [[['_route' => 'warehouse_create', '_controller' => 'App\\Warehouse\\Application\\WarehouseController::add'], null, ['POST' => 0], null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/_(?'
                    .'|wdt/([^/]++)(*:24)'
                    .'|profiler/([^/]++)(?'
                        .'|/(?'
                            .'|search/results(*:69)'
                            .'|router(*:82)'
                            .'|exception(?'
                                .'|(*:101)'
                                .'|\\.css(*:114)'
                            .')'
                        .')'
                        .'|(*:124)'
                    .')'
                .')'
                .'|/c(?'
                    .'|ategory/(?'
                        .'|edit/([^/]++)(*:163)'
                        .'|delete/([^/]++)(*:186)'
                    .')'
                    .'|ompany/(?'
                        .'|edit/([^/]++)(*:218)'
                        .'|delete/([^/]++)(*:241)'
                    .')'
                .')'
                .'|/local/(?'
                    .'|edit/([^/]++)(*:274)'
                    .'|delete/([^/]++)(*:297)'
                .')'
                .'|/product/(?'
                    .'|edit/([^/]++)(*:331)'
                    .'|delete/([^/]++)(*:354)'
                .')'
                .'|/s(?'
                    .'|ale(?'
                        .'|/(?'
                            .'|edit/([^/]++)(*:391)'
                            .'|delete/([^/]++)(*:414)'
                        .')'
                        .'|detail/(?'
                            .'|edit/([^/]++)(*:446)'
                            .'|delete/([^/]++)(*:469)'
                        .')'
                    .')'
                    .'|upplier/(?'
                        .'|edit/([^/]++)(*:503)'
                        .'|delete/([^/]++)(*:526)'
                    .')'
                .')'
                .'|/transaction/(?'
                    .'|edit/([^/]++)(*:565)'
                    .'|delete/([^/]++)(*:588)'
                .')'
                .'|/u(?'
                    .'|nit/(?'
                        .'|edit/([^/]++)(*:622)'
                        .'|delete/([^/]++)(*:645)'
                    .')'
                    .'|ser/(?'
                        .'|edit/([^/]++)(*:674)'
                        .'|delete/([^/]++)(*:697)'
                    .')'
                .')'
                .'|/voucher(?'
                    .'|series/(?'
                        .'|edit/([^/]++)(*:741)'
                        .'|delete/([^/]++)(*:764)'
                    .')'
                    .'|type/(?'
                        .'|edit/([^/]++)(*:794)'
                        .'|delete/([^/]++)(*:817)'
                    .')'
                .')'
                .'|/warehouse/(?'
                    .'|edit/([^/]++)(*:854)'
                    .'|delete/([^/]++)(*:877)'
                .')'
            .')/?$}sDu',
    ],
    [ // $dynamicRoutes
        24 => [[['_route' => '_wdt', '_controller' => 'web_profiler.controller.profiler::toolbarAction'], ['token'], null, null, false, true, null]],
        69 => [[['_route' => '_profiler_search_results', '_controller' => 'web_profiler.controller.profiler::searchResultsAction'], ['token'], null, null, false, false, null]],
        82 => [[['_route' => '_profiler_router', '_controller' => 'web_profiler.controller.router::panelAction'], ['token'], null, null, false, false, null]],
        101 => [[['_route' => '_profiler_exception', '_controller' => 'web_profiler.controller.exception_panel::body'], ['token'], null, null, false, false, null]],
        114 => [[['_route' => '_profiler_exception_css', '_controller' => 'web_profiler.controller.exception_panel::stylesheet'], ['token'], null, null, false, false, null]],
        124 => [[['_route' => '_profiler', '_controller' => 'web_profiler.controller.profiler::panelAction'], ['token'], null, null, false, true, null]],
        163 => [[['_route' => 'category_update', '_controller' => 'App\\Category\\Application\\CategoryController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        186 => [[['_route' => 'category_delete', '_controller' => 'App\\Category\\Application\\CategoryController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        218 => [[['_route' => 'company_update', '_controller' => 'App\\Company\\Application\\CompanyController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        241 => [[['_route' => 'company_delete', '_controller' => 'App\\Company\\Application\\CompanyController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        274 => [[['_route' => 'local_update', '_controller' => 'App\\Local\\Application\\LocalController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        297 => [[['_route' => 'local_delete', '_controller' => 'App\\Local\\Application\\LocalController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        331 => [[['_route' => 'product_update', '_controller' => 'App\\Product\\Application\\ProductController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        354 => [[['_route' => 'product_delete', '_controller' => 'App\\Product\\Application\\ProductController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        391 => [[['_route' => 'sale_update', '_controller' => 'App\\Sale\\Application\\SaleController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        414 => [[['_route' => 'sale_delete', '_controller' => 'App\\Sale\\Application\\SaleController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        446 => [[['_route' => 'saledetail_update', '_controller' => 'App\\SaleDetail\\Application\\SaleDetailController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        469 => [[['_route' => 'saledetail_delete', '_controller' => 'App\\SaleDetail\\Application\\SaleDetailController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        503 => [[['_route' => 'supplier_update', '_controller' => 'App\\Supplier\\Application\\SupplierController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        526 => [[['_route' => 'supplier_delete', '_controller' => 'App\\Supplier\\Application\\SupplierController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        565 => [[['_route' => 'transaction_update', '_controller' => 'App\\Transaction\\Application\\TransactionController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        588 => [[['_route' => 'transaction_delete', '_controller' => 'App\\Transaction\\Application\\TransactionController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        622 => [[['_route' => 'unit_update', '_controller' => 'App\\Unit\\Application\\UnitController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        645 => [[['_route' => 'unit_delete', '_controller' => 'App\\Unit\\Application\\UnitController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        674 => [[['_route' => 'user_update', '_controller' => 'App\\User\\Application\\UserController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        697 => [[['_route' => 'user_delete', '_controller' => 'App\\User\\Application\\UserController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        741 => [[['_route' => 'voucherseries_update', '_controller' => 'App\\VoucherSeries\\Application\\VoucherSeriesController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        764 => [[['_route' => 'voucherseries_delete', '_controller' => 'App\\VoucherSeries\\Application\\VoucherSeriesController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        794 => [[['_route' => 'vouchertype_update', '_controller' => 'App\\VoucherType\\Application\\VoucherTypeController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        817 => [[['_route' => 'vouchertype_delete', '_controller' => 'App\\VoucherType\\Application\\VoucherTypeController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        854 => [[['_route' => 'warehouse_update', '_controller' => 'App\\Warehouse\\Application\\WarehouseController::edit'], ['id'], ['POST' => 0], null, false, true, null]],
        877 => [
            [['_route' => 'warehouse_delete', '_controller' => 'App\\Warehouse\\Application\\WarehouseController::delete'], ['id'], ['POST' => 0], null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
