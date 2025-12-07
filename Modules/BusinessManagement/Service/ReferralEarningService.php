<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Modules\BusinessManagement\Repository\ReferralEarningSettingRepositoryInterface;
use Modules\BusinessManagement\Service\Interface\ReferralEarningServiceInterface;

class ReferralEarningService extends BaseService implements Interface\ReferralEarningServiceInterface
{
    protected $referralEarningSettingRepository;

    public function __construct(ReferralEarningSettingRepositoryInterface $referralEarningSettingRepository)
    {
        parent::__construct($referralEarningSettingRepository);
        $this->referralEarningSettingRepository = $referralEarningSettingRepository;
    }

    public function storeInfo(array $data)
    {
        $customerReferralEarningStatus = $this->referralEarningSettingRepository->findOneBy(criteria: [
            'key_name' => 'referral_earning_status',
            'settings_type' => CUSTOMER
        ]);
        if (array_key_exists('customer_referral_earning_status', $data)) {
            $customerReferralEarningStatusValue = 1;
        } else {
            $customerReferralEarningStatusValue = 0;
        }
        if ($customerReferralEarningStatus) {
            $this->referralEarningSettingRepository->update(id: $customerReferralEarningStatus->id, data: ['key_name' => 'referral_earning_status', 'settings_type' => CUSTOMER, 'value' => $customerReferralEarningStatusValue]);
        } else {
            $this->referralEarningSettingRepository->create(data: ['key_name' => 'referral_earning_status', 'settings_type' => CUSTOMER, 'value' => $customerReferralEarningStatusValue]);
        }

        $customerShareCodeEarning = $this->referralEarningSettingRepository->findOneBy(criteria: [
            'key_name' => 'share_code_earning',
            'settings_type' => CUSTOMER
        ]);
        $customerShareCodeEarningValue = $data["customer_share_code_earning"]??"";
        if ($customerShareCodeEarning) {
            $this->referralEarningSettingRepository->update(id: $customerShareCodeEarning->id, data: ['key_name' => 'share_code_earning', 'settings_type' => CUSTOMER, 'value' => $customerShareCodeEarningValue]);
        } else {
            $this->referralEarningSettingRepository->create(data: ['key_name' => 'share_code_earning', 'settings_type' => CUSTOMER, 'value' => $customerShareCodeEarningValue]);
        }


        $customerUseCodeEarning = $this->referralEarningSettingRepository->findOneBy(criteria: [
            'key_name' => 'use_code_earning',
            'settings_type' => CUSTOMER
        ]);
        $customerUseCodeEarningValue = [];
        if (array_key_exists('customer_first_ride_discount_status', $data)) {
            $customerUseCodeEarningValue['first_ride_discount_status'] = 1;
        } else {
            $customerUseCodeEarningValue['first_ride_discount_status'] = 0;
        }
        $customerUseCodeEarningValue['discount_amount'] = $data['customer_discount_amount'] ?? '';
        $customerUseCodeEarningValue['discount_amount_type'] = $data['customer_discount_amount_type'] ?? "";
        $customerUseCodeEarningValue['discount_validity'] = $data['customer_discount_validity'] ?? "";
        $customerUseCodeEarningValue['discount_validity_type'] = $data['customer_discount_validity_type'] ?? "";
        if ($customerUseCodeEarning) {
            $this->referralEarningSettingRepository->update(id: $customerUseCodeEarning->id, data: ['key_name' => 'use_code_earning', 'settings_type' => CUSTOMER, 'value' => $customerUseCodeEarningValue]);
        } else {
            $this->referralEarningSettingRepository->create(data: ['key_name' => 'use_code_earning', 'settings_type' => CUSTOMER, 'value' => $customerUseCodeEarningValue]);
        }
        $driverReferralEarningStatus = $this->referralEarningSettingRepository->findOneBy(criteria: [
            'key_name' => 'referral_earning_status',
            'settings_type' => DRIVER
        ]);
        if (array_key_exists('driver_referral_earning_status', $data)) {
            $driverReferralEarningStatusValue = 1;
        } else {
            $driverReferralEarningStatusValue = 0;
        }
        if ($driverReferralEarningStatus) {
            $this->referralEarningSettingRepository->update(id: $driverReferralEarningStatus->id, data: ['key_name' => 'referral_earning_status', 'settings_type' => DRIVER, 'value' => $driverReferralEarningStatusValue]);
        } else {
            $this->referralEarningSettingRepository->create(data: ['key_name' => 'referral_earning_status', 'settings_type' => DRIVER, 'value' => $driverReferralEarningStatusValue]);
        }


        $driverShareCodeEarning = $this->referralEarningSettingRepository->findOneBy(criteria: [
            'key_name' => 'share_code_earning',
            'settings_type' => DRIVER
        ]);
        $driverShareCodeEarningValue = $data["driver_share_code_earning"]??"";
        if ($driverShareCodeEarning) {
            $this->referralEarningSettingRepository->update(id: $driverShareCodeEarning->id, data: ['key_name' => 'share_code_earning', 'settings_type' => DRIVER, 'value' => $driverShareCodeEarningValue]);
        } else {
            $this->referralEarningSettingRepository->create(data: ['key_name' => 'share_code_earning', 'settings_type' => DRIVER, 'value' => $driverShareCodeEarningValue]);
        }

        $driverUseCodeEarning = $this->referralEarningSettingRepository->findOneBy(criteria: [
            'key_name' => 'use_code_earning',
            'settings_type' => DRIVER
        ]);
        $driverUseCodeEarningValue = $data["driver_use_code_earning"]??"";
        if ($driverUseCodeEarning) {
            $this->referralEarningSettingRepository->update(id: $driverUseCodeEarning->id, data: ['key_name' => 'use_code_earning', 'settings_type' => DRIVER, 'value' => $driverUseCodeEarningValue]);
        } else {
            $this->referralEarningSettingRepository->create(data: ['key_name' => 'use_code_earning', 'settings_type' => DRIVER, 'value' => $driverUseCodeEarningValue]);
        }
    }
}
