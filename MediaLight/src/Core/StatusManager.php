<?php

declare(strict_types=1);

namespace MediaLight\Core;

use MediaLight\Models\HyperHDR\Status;

final class StatusManager
{
    /**
     * @var callable(string, mixed): void
     */
    private $valueWriter;

    /**
     * @param callable(string, mixed): void $valueWriter
     */
    public function __construct(
        callable $valueWriter,
        private readonly Logger $logger
    ) {
        $this->valueWriter = $valueWriter;
    }

    public function applyHyperHDR(Status $status): void
    {
        $values = [
            'HyperHDROnline'             => $status->isOnline(),
            'HyperHDRVersion'            => $status->getVersion(),
            'HyperHDRHostname'           => $status->getHostname(),
            'HyperHDRCurrentInstance'    => $status->getCurrentInstance(),
            'HyperHDRInstanceName'       => $status->getInstanceName(),
            'HyperHDRInstanceEnabled'    => $status->isInstanceEnabled(),
            'HyperHDRGrabberEnabled'     => $status->isGrabberEnabled(),
            'HyperHDRGrabberDevice'      => $status->getGrabberDevice(),
            'HyperHDRVideoMode'          => $status->getVideoMode(),
            'HyperHDRLEDDeviceEnabled'   => $status->isLedDeviceEnabled(),
            'HyperHDRSmoothingEnabled'   => $status->isSmoothingEnabled(),
            'HyperHDRHDREnabled'         => $status->isHdrEnabled(),
            'HyperHDRBlackBorderEnabled' => $status->isBlackBorderEnabled(),
            'HyperHDRForwarderEnabled'   => $status->isForwarderEnabled(),
            'HyperHDRFPS'                => $status->getFps(),
            'HyperHDRVisiblePriority'    => $status->getVisiblePriority(),
            'HyperHDRPriorityComponent'  => $status->getPriorityComponent(),
            'HyperHDRPriorityOwner'      => $status->getPriorityOwner(),
            'HyperHDREffectCount'        => $status->getEffectCount(),
            'HyperHDRLEDCount'           => $status->getLedCount(),
            'HyperHDRWLEDConnected'      => $status->isWledConnected(),
            'HyperHDRSessionCount'       => $status->getSessionCount(),
            'HyperHDRLastError'          => $status->getLastError()
        ];

        foreach ($values as $ident => $value) {
            ($this->valueWriter)($ident, $value);
        }

        $this->logger->debug(
            'HyperHDR-Statusvariablen aktualisiert',
            $values
        );
    }

    public function resetHyperHDR(): void
    {
        $values = [
            'HyperHDROnline'             => false,
            'HyperHDRVersion'            => '',
            'HyperHDRHostname'           => '',
            'HyperHDRCurrentInstance'    => -1,
            'HyperHDRInstanceName'       => '',
            'HyperHDRInstanceEnabled'    => false,
            'HyperHDRGrabberEnabled'     => false,
            'HyperHDRGrabberDevice'      => '',
            'HyperHDRVideoMode'          => '',
            'HyperHDRLEDDeviceEnabled'   => false,
            'HyperHDRSmoothingEnabled'   => false,
            'HyperHDRHDREnabled'         => false,
            'HyperHDRBlackBorderEnabled' => false,
            'HyperHDRForwarderEnabled'   => false,
            'HyperHDRFPS'                => 0.0,
            'HyperHDRVisiblePriority'    => -1,
            'HyperHDRPriorityComponent'  => '',
            'HyperHDRPriorityOwner'      => '',
            'HyperHDREffectCount'        => 0,
            'HyperHDRLEDCount'           => 0,
            'HyperHDRWLEDConnected'      => false,
            'HyperHDRSessionCount'       => 0,
            'HyperHDRLastError'          => ''
        ];

        foreach ($values as $ident => $value) {
            ($this->valueWriter)($ident, $value);
        }
    }
}