<?php

namespace MgGoGetSsl\Service;

use MgGoGetSsl\Facade\Lang;

/**
 * @property Record $Record
 */
class PackageService
{
    const PACKAGES_TABLE = 'packages';
    const PACKAGE_NAMES_TABLE = 'package_names';
    const PRICINGS_TABLE = 'pricings';
    const PACKAGE_PRICING_TABLE = 'package_pricing';
    const PACKAGE_META_TABLE = 'package_meta';
    const PACKAGE_GROUPS_TABLE = 'package_groups';
    const PACKAGE_GROUP_TABLE = 'package_group';
    const PACKAGE_GROUP_NAMES = 'package_group_names';

    const PACKAGE_STATUS_ACTIVE = 'active';
    const PACKAGE_STATUS_INACTIVE = 'inactive';
    const PACKAGE_STATUS_RESTRICTED = 'restricted';

    private string $language;

    /**
     * PackageService constructor
     */
    public function __construct()
    {
        \Loader::loadComponents($this, ['Record']);
        $this->language = (new BlestaService())->getLanguage();
    }

    /**
     * @throws \Exception
     * @throws \RuntimeException
     * @param array $data
     */
    public function savePackage(array $data)
    {

        $this->Record->begin();

        if (!isset($data['name']) || empty($data['name'])) {
            throw new \RuntimeException(Lang::translate('product_name_empty'));
        }
        if (!isset($data['module_id']) || empty($data['module_id'])) {
            throw new \RuntimeException(Lang::translate('module_id_empty'));
        }

        if (!isset($data['company_id'])) {
            $data['company_id'] = (new BlestaService())->getCompanyId();
        }
        if (!isset($data['id_format']) || empty($data['id_format'])) {
            $data['id_format'] = '{num}';
        }
        if (!isset($data['id_value']) || empty($data['id_value'])) {
            $lastPackage = $this->Record
                ->select()
                ->from(self::PACKAGES_TABLE)
                ->order(['id_value' => 'DESC'])
                ->limit(1, 0)
                ->fetch();
            
            $data['id_value'] = $lastPackage ? $lastPackage->id_value + 1 : 1;
        }
        if (!isset($data['taxable']) || empty($data['taxable'])) {
            $data['taxable'] = 0;
        }
        if (!isset($data['single_term']) || empty($data['single_term'])) {
            $data['single_term'] = 0;
        }
        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = self::PACKAGE_STATUS_INACTIVE;
        }

        try {
            $pricingData = isset($data['pricing']) && is_array($data['pricing']) ? $data['pricing'] : [];
            if (isset($data['pricing'])) {
                unset($data['pricing']);
            }

            $metaData = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
            if (isset($data['meta'])) {
                unset($data['meta']);
            }

            $groups = isset($data['groups']) && is_array($data['groups']) ? $data['groups'] : [];
            if (isset($data['groups'])) {
                unset($data['groups']);
            }

            $this->Record->insert(self::PACKAGES_TABLE, [
                'id_format'   => $data['id_format'],
                'id_value'    => $data['id_value'],
                'module_id'   => $data['module_id'],
                'module_row'  => $data['module_row'],
                'taxable'     => $data['taxable'],
                'single_term' => $data['single_term'],
                'status'      => $data['status'],
                'company_id'  => $data['company_id']
            ]);

            $packageId = $this->Record->lastInsertId();

            $this->savePackageName($packageId, $data);
            $this->savePackagePricing($packageId, $pricingData);
            $this->savePackageMeta($packageId, $metaData);
            $this->savePackageGroups($packageId, $groups);
        } catch (\Exception $e) {
            $this->Record->rollback();
            throw $e;
        }

        $this->Record->commit();
    }

    /**
     * @throws \Exception
     * @param mixed $package
     * @param array $data
     */
    public function updatePackage($package, array $data)
    {
        $packageId = is_object($package) ? $package->id : $package;
        $this->Record->begin();

        try {
            $pricingData = isset($data['pricing']) && is_array($data['pricing']) ? $data['pricing'] : [];
            if (isset($data['pricing'])) {
                unset($data['pricing']);
            }

            $metaData = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
            if (isset($data['meta'])) {
                unset($data['meta']);
            }

            $groups = isset($data['groups']) && is_array($data['groups']) ? $data['groups'] : [];
            if (isset($data['groups'])) {
                unset($data['groups']);
            }

            $name = isset($data['name']) ? $data['name'] : null;
            $status = isset($data['status']) ? $data['status'] : null;

            if ($pricingData) {
                $this->removePackagePricing($packageId);
                $this->savePackagePricing($packageId, $pricingData);
            }
            if ($metaData) {
                $this->updatePackageMeta($packageId, $metaData);
            }
            if ($groups) {
                $this->removePackageGroups($packageId);
                $this->savePackageGroups($packageId, $groups);
            }
            if (!empty($name)) {
                $this->updatePackageName($packageId, $data);
            }

            if (!empty($status)) {
                $this->updatePackageStatus($packageId, $data);
            }


        } catch (\Exception $e) {
            $this->Record->rollback();
            throw $e;
        }

        $this->Record->commit();
    }

    /**
     * @throws \RuntimeException
     * @param int $packageId
     * @return \stdClass
     */
    public function getPackage($packageId)
    {
        if (!($package = $this->Record
            ->select()
            ->from(self::PACKAGES_TABLE)
            ->where('id', '=', $packageId)
            ->fetch()
        )) {
            throw new \RuntimeException(Lang::translate('entity_not_found'));
        }

        return $package;
    }

    /**
     * @param int|null $moduleId
     * @param bool $hydratePricing
     * @param bool $hydrateMeta
     * @param bool $hydrateGroups
     * @return array
     */
    public function getPackages($moduleId = null, $hydratePricing = true, $hydrateMeta = true, $hydrateGroups = true)
    {
        $language = (new BlestaService())->getLanguage();
        $builder = $this->Record
            ->select()
            ->from(self::PACKAGES_TABLE)
            ->innerJoin(self::PACKAGE_NAMES_TABLE, self::PACKAGES_TABLE.'.id', '=', self::PACKAGE_NAMES_TABLE.'.package_id', false)
            ->where(self::PACKAGE_NAMES_TABLE.'.lang', '=', $language);

        if (!empty($moduleId)) {
            $builder->where('module_id', '=', $moduleId);
        }

        $packages = $builder->fetchAll();

        foreach ($packages as $package) {
            $package->pricing = $hydratePricing ? $this->Record
                ->select(['pricings.*'])
                ->from(self::PRICINGS_TABLE)
                ->innerJoin(self::PACKAGE_PRICING_TABLE, 'package_pricing.pricing_id', '=', 'pricings.id', false)
                ->where('package_pricing.package_id', '=', $package->id)
                ->fetchAll() : [];
            $package->meta = $hydrateMeta ? $this->Record
                ->select()
                ->from(self::PACKAGE_META_TABLE)
                ->where('package_id', '=', $package->id)
                ->fetchAll() : [];
            $package->groups = $hydrateGroups ? $this->Record
                ->select(['package_groups.*'])
                ->from(self::PACKAGE_GROUPS_TABLE)
                ->innerJoin(self::PACKAGE_GROUP_TABLE, 'package_group.package_group_id', '=', 'package_groups.id', false)
                ->where('package_group.package_id', '=', $package->id)
                ->fetchAll() : [];
        }

        return $packages;
    }

    /**
     * @param int|null $companyId
     * @return array
     */
    public function getPackageGroups($companyId = null)
    {
        if (empty($companyId)) {
            $companyId = (new BlestaService())->getCompanyId();
        }

        return $this->Record
            ->select()
            ->from(self::PACKAGE_GROUPS_TABLE)
            ->innerJoin(self::PACKAGE_GROUP_NAMES, 'package_group_names.package_group_id', '=', 'package_groups.id', false)
            ->where('package_groups.company_id', '=', $companyId)
            ->where('package_group_names.lang', '=', $this->language)
            ->fetchAll();
    }

    /**
     * @param int|null $companyId
     * @return array
     */
    public function getPackageGroupsAsAssoc($companyId = null)
    {
        $packagesArray = [];

        foreach ($this->getPackageGroups($companyId) as $packageGroup) {
            $packagesArray[$packageGroup->id] = $packageGroup->name;
        }

        return $packagesArray;
    }

    /**
     * @param int $packageId
     */
    public function removePackagePricing($packageId)
    {
        foreach ($this->Record
            ->select()
            ->from(self::PACKAGE_PRICING_TABLE)
            ->where('package_id', '=', $packageId)
            ->fetchAll() as $packagePricing
        ) {
            $this->Record
                ->from(self::PRICINGS_TABLE)
                ->where('id', '=', $packagePricing->pricing_id)
                ->delete();

            $this->Record
                ->from(self::PACKAGE_PRICING_TABLE)
                ->where('id', '=', $packagePricing->id)
                ->delete();
        }
    }

    /**
     * @param int $packageId
     * @param array $pricingData
     */
    private function savePackagePricing($packageId, array $pricingData)
    {
        foreach ($pricingData as $pricing) {
            if (isset($pricing['company_id']) || empty($pricing['company_id'])) {
                $pricing['company_id'] = (new BlestaService())->getCompanyId();
            }
            if (!isset($pricing['price']) || empty($pricing['price'])) {
                $pricing['price'] = 0.0;
            }
            if (!isset($pricing['setup_fee']) || empty($pricing['setup_fee'])) {
                $pricing['setup_fee'] = 0.0;
            }
            if (!isset($pricing['cancel_fee']) || empty($pricing['cancel_fee'])) {
                $pricing['cancel_fee'] = 0.0;
            }

            $this->Record->insert(self::PRICINGS_TABLE, $pricing);
            $this->Record->insert(self::PACKAGE_PRICING_TABLE, [
                'package_id' => $packageId,
                'pricing_id' => $this->Record->lastInsertId()
            ]);
        }
    }

    /**
     * @param int $packageId
     * @param array $metaData
     */
    private function savePackageMeta($packageId, array $metaData)
    {
        foreach ($metaData as $meta) {
            if (!isset($meta['serialized']) || empty($meta['serialized'])) {
                $meta['serialized'] = 0;
            }
            if (!isset($meta['encrypted']) || empty($meta['encrypted'])) {
                $meta['encrypted'] = 0;
            }

            $meta['package_id'] = $packageId;

            $this->Record->insert(self::PACKAGE_META_TABLE, $meta);
        }
    }

    /**
     * @param int    $packageId
     * @param array $metaData
     */
    private function updatePackageMeta($packageId, array $metaData)
    {
        foreach ($metaData as $meta) {
            if (!isset($meta['serialized']) || empty($meta['serialized'])) {
                $meta['serialized'] = 0;
            }
            if (!isset($meta['encrypted']) || empty($meta['encrypted'])) {
                $meta['encrypted'] = 0;
            }

            if ($currentMeta = $this->Record
                ->select()
                ->from(self::PACKAGE_META_TABLE)
                ->where('key', '=', $meta['key'])
                ->where('package_id', '=', $packageId)
                ->fetch()
            ) {
                $this->Record
                    ->where('key', '=', $meta['key'])
                    ->where('package_id', '=', $packageId)
                    ->update(self::PACKAGE_META_TABLE, $meta);
            } else {
                $meta['package_id'] = $packageId;

                $this->Record->insert(self::PACKAGE_META_TABLE, $meta);
            }
        }
    }

    /**
     * @param int $packageId
     */
    private function removePackageGroups($packageId)
    {
        $this->Record
            ->from(self::PACKAGE_GROUP_TABLE)
            ->where('package_id', '=', $packageId)
            ->delete();
    }

    /**
     * @param int   $packageId
     * @param array $groupIds
     */
    private function savePackageGroups($packageId, array $groupIds)
    {
        foreach ($groupIds as $groupId) {
            $this->Record->insert(self::PACKAGE_GROUP_TABLE, [
                'package_id'       => $packageId,
                'package_group_id' => $groupId,
                'order'            => 0
            ]);
        }
    }

    /**
     * @param int   $packageId
     * @param array $data
     */
    private function savePackageName($packageId, array $data)
    {
        $this->Record->insert(self::PACKAGE_NAMES_TABLE , [
            'package_id' => $packageId,
            'lang'       => $this->language,
            'name'       => $data['name'],
        ]);
    }

    /**
     * @param int   $packageId
     * @param array $data
     */
    private function updatePackageName($packageId, array $data)
    {
        $this->Record
            ->where('package_id', '=', $packageId)
            ->where('lang', '=', $this->language)
            ->update(self::PACKAGE_NAMES_TABLE, [
                'name' => $data['name'],
            ]);
    }

    /**
     * @param int   $packageId
     * @param array $data
     */
    private function updatePackageStatus($packageId, array $data)
    {
        $this->Record
            ->where('id', '=', $packageId)
            ->update(self::PACKAGES_TABLE, [
                'status' => $data['status'],
            ]);
    }
}
