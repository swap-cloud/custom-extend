<?php

namespace SwapCloud\CustomExtend\Extend;

use Slowlyo\OwlAdmin\Extend\Manager as BaseManager;
use Slowlyo\OwlAdmin\Models\Extension as ExtensionModel;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Container\Container;

class Manager extends BaseManager
{
    public static function wrap($base, Container $app): self
    {
        $manager = new self($app);

        if (!$base instanceof BaseManager) {
            return $manager;
        }

        $ref = new \ReflectionClass(BaseManager::class);

        foreach (['extensions', 'extensionPaths', 'settings', 'files', 'app'] as $propertyName) {
            if (!$ref->hasProperty($propertyName)) {
                continue;
            }

            $prop = $ref->getProperty($propertyName);
            $prop->setAccessible(true);

            if ($propertyName === 'app') {
                $prop->setValue($manager, $app);
                continue;
            }

            $prop->setValue($manager, $prop->getValue($base));
        }

        return $manager;
    }

    public function all()
    {
        $this->load();

        return parent::all();
    }

    /**
     * 获取配置.
     *
     * @return ExtensionModel|Collection
     * @throws \Exception
     */
    public function settings()
    {
        try {
            $settings = ExtensionModel::keyByNameList();
        } catch (\Throwable $e) {
            $settings = new Collection();
        }

        $this->settings = $settings;

        return $this->settings;
    }
}
