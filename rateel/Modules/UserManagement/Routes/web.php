<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\CashCollectController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Customer\CustomerController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Customer\CustomerLevelController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Customer\CustomerWalletController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver\DriverController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver\DriverLevelController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver\WithdrawalController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver\WithdrawRequestController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Employee\EmployeeController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\Employee\EmployeeRoleController;
use Modules\UserManagement\Http\Controllers\Web\New\Admin\LevelAccessController;
use Modules\UserManagement\Http\Controllers\Web\Api\UserSearchController;
use Modules\UserManagement\Http\Controllers\Web\Admin\HotspotController;
use Modules\UserManagement\Http\Controllers\Web\Admin\ReferralController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    
    // AJAX User Search endpoints (for Select2/autocomplete dropdowns)
    Route::group(['prefix' => 'api', 'as' => 'api.'], function () {
        Route::controller(UserSearchController::class)->group(function () {
            Route::get('search-customers', 'searchCustomers')->name('search-customers');
            Route::get('search-drivers', 'searchDrivers')->name('search-drivers');
            Route::get('get-customer', 'getCustomer')->name('get-customer');
            Route::get('get-driver', 'getDriver')->name('get-driver');
        });
    });

    Route::group(['prefix' => 'customer', 'as' => 'customer.'], function () {
        Route::controller(CustomerController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::get('show/{id}', 'show')->name('show');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::put('update/{id}', 'update')->name('update');
            Route::get('update-status', 'updateStatus')->name('update-status');
            Route::get('get-all-ajax', 'getAllAjax')->name('get-all-ajax');
            Route::get('statistics', 'statistics')->name('statistics');
            Route::get('log', 'log')->name('log');
            Route::get('export', 'export')->name('export');
            Route::get('transaction-export/{id}', 'customerTransactionExport')->name('transaction-export');
            Route::get('trash', 'trash')->name('trash');
            Route::get('restore/{id}', 'restore')->name('restore');
            Route::get('get-level-wise-customer', 'getLevelWiseCustomer')->name('get-level-wise-customer');
            Route::delete('permanent-delete/{id}', 'permanentDelete')->name('permanent-delete');
        });
        Route::group(['prefix' => 'level', 'as' => 'level.'], function () {
            Route::controller(CustomerLevelController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::delete('delete/{id}', 'destroy')->name('delete');
                Route::put('update/{id}', 'update')->name('update');
                Route::get('update-status', 'updateStatus')->name('update-status');
                Route::get('export', 'export')->name('export');
                Route::get('log', 'log')->name('log');
                Route::get('trash', 'trash')->name('trash');
                Route::get('restore/{id}', 'restore')->name('restore');
                Route::delete('permanent-delete/{id}', 'permanentDelete')->name('permanent-delete');
                Route::get('statistics', 'statistics')->name('statistics');
            });
            Route::group(['prefix' => 'access', 'as' => 'access.'], function () {
                Route::controller(LevelAccessController::class)->group(function () {
                    Route::get('store', 'store')->name('store');
                });
            });
        });
        Route::group(['prefix' => 'wallet', 'as' => 'wallet.'], function () {
            Route::controller(CustomerWalletController::class)->group(function () {
                Route::any('index', 'index')->name('index');
                Route::post('store', 'store')->name('store');
                Route::get('export', 'export')->name('export');
            });
        });
    });

    Route::group(['prefix' => 'driver', 'as' => 'driver.'], function () {
        Route::controller(DriverController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::get('profile-update-request-list', 'profileUpdateRequestList')->name('profile-update-request-list');
            Route::get('profile-update-request-list-export', 'profileUpdateRequestListExport')->name('profile-update-request-list-export');
            Route::post('profile-update-request-approved-rejected/{id}', 'profileUpdateRequestApprovedOrRejected')->name('profile-update-request-approved-rejected');
            Route::get('show/{id}', 'show')->name('show');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::delete('permanent-delete/{id}', 'permanentDelete')->name('permanent-delete');
            Route::put('update/{id}', 'update')->name('update');
            Route::get('update-status', 'updateStatus')->name('update-status');
            Route::get('get-all-ajax', 'getAllAjax')->name('get-all-ajax');
            Route::get('get-all-ajax-vehicle', 'getAllAjaxVehicle')->name('get-all-ajax-vehicle');
            Route::get('statistics', 'statistics')->name('statistics');
            Route::get('log', 'log')->name('log');
            Route::get('trash', 'trash')->name('trash');
            Route::get('restore/{id}', 'restore')->name('restore');
            Route::get('export', 'export')->name('export');
            Route::get('transaction-export', 'driverTransactionExport')->name('transaction-export');
        });


        Route::group(['prefix' => 'cash', 'as' => 'cash.'], function () {
            Route::get('/{id}', [CashCollectController::class, 'show'])->name('index');
            Route::post('collect/{id}', [CashCollectController::class, 'collect'])->name('collect');
        });

        Route::group(['prefix' => 'level', 'as' => 'level.'], function () {
            Route::controller(DriverLevelController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::delete('delete/{id}', 'destroy')->name('delete');
                Route::put('update/{id}', 'update')->name('update');
                Route::get('update-status', 'updateStatus')->name('update-status');
                Route::get('statistics', 'statistics')->name('statistics');
                Route::get('log', 'log')->name('log');
                Route::get('export', 'export')->name('export');
                Route::get('trash', 'trash')->name('trash');
                Route::get('restore/{id}', 'restore')->name('restore');
                Route::delete('permanent-delete/{id}', 'permanentDelete')->name('permanent-delete');
            });

            Route::group(['prefix' => 'access', 'as' => 'access.'], function () {
                Route::controller(LevelAccessController::class)->group(function () {
                    Route::get('store', 'store')->name('store');
                });
            });
        });

        Route::controller(WithdrawalController::class)->group(function () {
            Route::group(['prefix' => 'withdraw-method', 'as' => 'withdraw-method.'], function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'store')->name('store');
                Route::post('update/{id}', 'update')->name('update');
                Route::delete('delete/{id}', 'destroy')->name('delete');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::get('default-status-update', 'statusUpdate')->name('default-status-update');
                Route::get('active-status-update', 'activeUpdate')->name('active-status-update');
            });
        });
        Route::controller(WithdrawRequestController::class)->group(function () {
            Route::group(['prefix' => 'withdraw', 'as' => 'withdraw.'], function () {
                Route::get('requests', 'index')->name('requests');
                Route::get('request-details/{id}', 'requestDetails')->name('request-details');
                Route::post('action/{id}', 'action')->name('action');
                Route::post('multiple-action', 'multipleAction')->name('multiple-action');
                Route::get('single-invoice/{id}', 'singleInvoice')->name('single-invoice');
                Route::get('multiple-invoice', 'multipleInvoice')->name('multiple-invoice');
            });
        });

        // Travel Approval System - Enterprise-grade
        Route::group(['prefix' => 'travel-approval', 'as' => 'travel-approval.'], function () {
            Route::controller(\Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver\TravelApprovalController::class)->group(function () {
                Route::get('/', 'index')->name('index');                          // List travel requests
                Route::post('approve/{id}', 'approve')->name('approve');          // Approve travel request
                Route::post('reject/{id}', 'reject')->name('reject');             // Reject travel request
                Route::post('revoke/{id}', 'revoke')->name('revoke');             // Revoke approved privilege
                Route::post('bulk-approve', 'bulkApprove')->name('bulk-approve'); // Bulk approve
                Route::get('statistics', 'statistics')->name('statistics');       // Dashboard stats
            });
        });

        // Driver Onboarding Approval System (Uber-style)
        Route::group(['prefix' => 'approvals', 'as' => 'approvals.'], function () {
            Route::controller(\Modules\UserManagement\Http\Controllers\Web\New\Admin\Driver\DriverApprovalController::class)->group(function () {
                Route::get('/', 'index')->name('index');                          // List pending applications
                Route::get('/{id}', 'show')->name('show');                        // View application details
                Route::post('/approve/{id}', 'approve')->name('approve');         // Approve driver
                Route::post('/reject/{id}', 'reject')->name('reject');            // Reject driver
                Route::post('/document/verify/{driverId}/{documentId}', 'verifyDocument')->name('document.verify'); // Verify individual document
                Route::post('/document/reject/{driverId}/{documentId}', 'rejectDocument')->name('document.reject'); // Reject individual document
                Route::post('/vehicle/approve/{driverId}/{vehicleId}', 'approveVehicle')->name('vehicle.approve'); // Approve individual vehicle
                Route::post('/vehicle/reject/{driverId}/{vehicleId}', 'rejectVehicle')->name('vehicle.reject'); // Reject individual vehicle
                Route::post('/vehicle/approve-primary/{driverId}/{vehicleId}', 'approvePrimaryVehicleChange')->name('vehicle.approve-primary'); // Approve primary vehicle change
                Route::post('/vehicle/reject-primary/{driverId}/{vehicleId}', 'rejectPrimaryVehicleChange')->name('vehicle.reject-primary'); // Reject primary vehicle change
                Route::post('/deactivate/{id}', 'deactivate')->name('deactivate'); // Deactivate driver
                Route::post('/reactivate/{id}', 'reactivate')->name('reactivate'); // Reactivate driver
            });
        });

    });

    Route::group(['prefix' => 'employee', 'as' => 'employee.'], function () {
        Route::controller(EmployeeController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::get('show/{id}', 'show')->name('show');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::put('update/{id}', 'update')->name('update');
            Route::get('update-status', 'updateStatus')->name('update-status');
            Route::get('export', 'export')->name('export');
            Route::get('log', 'log')->name('log');
            Route::get('trash', 'trash')->name('trash');
            Route::get('restore/{id}', 'restore')->name('restore');
            Route::delete('permanent-delete/{id}', 'permanentDelete')->name('permanent-delete');
        });


        Route::group(['prefix' => 'role', 'as' => 'role.'], function () {
            Route::controller(EmployeeRoleController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('store', 'store')->name('store');
                Route::get('update-status/{id}', 'updateStatus')->name('update-status');
                Route::delete('delete/{id}', 'destroy')->name('delete');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::put('update/{id}', 'update')->name('update');
                Route::get('get-roles', 'getRoles')->name('get-roles');
                Route::get('log', 'log')->name('log');
                Route::get('export', 'export')->name('export');
            });
        });
    });
    
    Route::group(['prefix' => 'hotspots', 'as' => 'hotspots.'], function () {
        Route::controller(HotspotController::class)->group(function () {
            Route::get('/', 'index')->name('index');
        });
    });

    // Referral Program Management
    Route::group(['prefix' => 'referral', 'as' => 'referral.'], function () {
        Route::controller(ReferralController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('settings', 'settings')->name('settings');
            Route::put('settings', 'updateSettings')->name('settings.update');
            Route::get('referrals', 'referrals')->name('referrals');
            Route::get('rewards', 'rewards')->name('rewards');
            Route::get('leaderboard', 'leaderboard')->name('leaderboard');
            Route::get('fraud-logs', 'fraudLogs')->name('fraud-logs');
            Route::get('show/{id}', 'show')->name('show');
            Route::post('block-user/{userId}', 'blockUser')->name('block-user');
            Route::get('export', 'export')->name('export');
        });
    });

});



