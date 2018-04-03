<?php

namespace Nemo64\Environment;


use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class EnvironmentBuilder implements PluginInterface, EventSubscriberInterface
{
    const PACKAGE_NAME = 'nemo64/environment';

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_UPDATE_CMD => 'execute',
            ScriptEvents::POST_INSTALL_CMD => 'execute',
        ];
    }

    public function execute(Event $event)
    {
        $localRepository = $event->getComposer()->getRepositoryManager()->getLocalRepository();

        // The plugin can run without the extension being installed.
        // To prevent this it must be checked that the extension is still installed.
        $isPluginInstalled = $localRepository->findPackage(self::PACKAGE_NAME, '*') !== null;
        if (!$isPluginInstalled) {
            return;
        }

        $configuratorClasses = ConfiguratorContainer::getClassesFromRepository($localRepository);
        $container = ConfiguratorContainer::createFromClassList($configuratorClasses);
        $container->configure($event->getComposer(), $event->getIO(), getcwd());
    }

    public function activate(Composer $composer, IOInterface $io)
    {
    }
}