<?php

declare(strict_types=1);

namespace MediaLight\Core;

use MediaLight\Models\HyperHDR\Status as HyperHDRStatus;
use MediaLight\Models\WLED\Bus;
use MediaLight\Models\WLED\Controller as WLEDController;
use MediaLight\Models\WLED\Segment;

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

    public function applyHyperHDR(HyperHDRStatus $status): void
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

        $this->writeValues($values);

        $this->logger->debug(
            'HyperHDR-Statusvariablen aktualisiert',
            $values
        );
    }

    public function resetHyperHDR(): void
    {
        $this->writeValues([
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
        ]);
    }

    public function applyWLED(WLEDController $controller): void
    {
        $state = $controller->getState();

        $values = [
            'WLEDOnline'         => $controller->isOnline(),
            'WLEDName'           => $controller->getName(),
            'WLEDFirmware'       => $controller->getFirmware(),
            'WLEDRelease'        => $controller->getRelease(),
            'WLEDArchitecture'   => $controller->getArchitecture(),
            'WLEDIPAddress'      => $controller->getIpAddress(),
            'WLEDMACAddress'     => $controller->getMacAddress(),
            'WLEDLEDCount'       => $controller->getLedCount(),
            'WLEDBusCount'       => $controller->getBusCount(),
            'WLEDRGBW'           => $controller->isRgbw(),
            'WLEDMaximumCurrent' => $controller->getMaximumCurrent(),
            'WLEDCurrentPower'   => $controller->getCurrentPower(),
            'WLEDFPS'            => $controller->getFramesPerSecond(),
            'WLEDEffectCount'    => $controller->getEffectCount(),
            'WLEDPaletteCount'   => $controller->getPaletteCount(),
            'WLEDUptime'         => $controller->getUptime(),
            'WLEDFreeHeap'       => $controller->getFreeHeap(),
            'WLEDRSSI'           => $controller->getRssi(),
            'WLEDSignalQuality'  => $controller->getSignalQuality(),
            'WLEDLiveMode'       => $controller->getLiveMode(),
            'WLEDLiveSourceIP'   => $controller->getLiveSourceIp(),
            'WLEDPower'          => $state->isOn(),
            'WLEDBrightness'     => $state->getBrightness(),
            'WLEDRealtime'       => $state->isRealtime(),
            'WLEDRealtimeMode'   => $state->getRealtimeOverride(),
            'WLEDSegmentCount'   => count($state->getSegments()),
            'WLEDUDPSend'        => $state->isUdpSendEnabled(),
            'WLEDUDPReceive'     => $state->isUdpReceiveEnabled()
        ];

        $this->writeValues($values);

        for ($busNumber = 1; $busNumber <= 4; $busNumber++) {
            $bus = $controller->getBus($busNumber - 1);

            if ($bus instanceof Bus) {
                $this->applyWLEDBus($busNumber, $bus);
            } else {
                $this->resetWLEDBus($busNumber);
            }
        }

        for ($busNumber = 1; $busNumber <= 4; $busNumber++) {
            $segment = $state->getSegment($busNumber - 1);

            if ($segment instanceof Segment) {
                $this->applyWLEDControlSegment(
                    $busNumber,
                    $segment
                );
            } else {
                $this->resetWLEDControlSegment($busNumber);
            }
        }

        $this->logger->debug(
            'WLED-Statusvariablen aktualisiert',
            $values
        );
    }

    public function resetWLED(): void
    {
        $this->writeValues([
            'WLEDOnline'         => false,
            'WLEDName'           => '',
            'WLEDFirmware'       => '',
            'WLEDRelease'        => '',
            'WLEDArchitecture'   => '',
            'WLEDIPAddress'      => '',
            'WLEDMACAddress'     => '',
            'WLEDLEDCount'       => 0,
            'WLEDBusCount'       => 0,
            'WLEDRGBW'           => false,
            'WLEDMaximumCurrent' => 0,
            'WLEDCurrentPower'   => 0,
            'WLEDFPS'            => 0,
            'WLEDEffectCount'    => 0,
            'WLEDPaletteCount'   => 0,
            'WLEDUptime'         => 0,
            'WLEDFreeHeap'       => 0,
            'WLEDRSSI'           => 0,
            'WLEDSignalQuality'  => 0,
            'WLEDLiveMode'       => '',
            'WLEDLiveSourceIP'   => '',
            'WLEDPower'          => false,
            'WLEDBrightness'     => 0,
            'WLEDRealtime'       => false,
            'WLEDRealtimeMode'   => 0,
            'WLEDSegmentCount'   => 0,
            'WLEDUDPSend'        => false,
            'WLEDUDPReceive'     => false
        ]);

        for ($busNumber = 1; $busNumber <= 4; $busNumber++) {
            $this->resetWLEDBus($busNumber);
        }

        for ($busNumber = 2; $busNumber <= 4; $busNumber++) {
            $this->resetWLEDControlSegment($busNumber);
        }
    }

    private function applyWLEDBus(
        int $busNumber,
        Bus $bus
    ): void {
        $prefix = 'WLEDBus' . $busNumber;

        $this->writeValues([
            $prefix . 'Available'       => true,
            $prefix . 'Start'           => $bus->getStart(),
            $prefix . 'Stop'            => $bus->getStop(),
            $prefix . 'Length'          => $bus->getLength(),
            $prefix . 'GPIO'            => $bus->getPrimaryPin(),
            $prefix . 'Pins'            => implode(
                ', ',
                array_map(
                    static fn (int $pin): string => (string) $pin,
                    $bus->getPins()
                )
            ),
            $prefix . 'Type'            => $bus->getType(),
            $prefix . 'ColorOrder'      => $bus->getColorOrder(),
            $prefix . 'Reversed'        => $bus->isReversed(),
            $prefix . 'Skip'            => $bus->getSkip(),
            $prefix . 'MilliAmpsPerLED' => $bus->getMilliAmpsPerLed(),
            $prefix . 'MaximumCurrent'  => $bus->getMaximumCurrent()
        ]);
    }

    private function resetWLEDBus(int $busNumber): void
    {
        $prefix = 'WLEDBus' . $busNumber;

        $this->writeValues([
            $prefix . 'Available'       => false,
            $prefix . 'Start'           => 0,
            $prefix . 'Stop'            => 0,
            $prefix . 'Length'          => 0,
            $prefix . 'GPIO'            => -1,
            $prefix . 'Pins'            => '',
            $prefix . 'Type'            => 0,
            $prefix . 'ColorOrder'      => 0,
            $prefix . 'Reversed'        => false,
            $prefix . 'Skip'            => 0,
            $prefix . 'MilliAmpsPerLED' => 0,
            $prefix . 'MaximumCurrent'  => 0
        ]);
    }

    private function applyWLEDControlSegment(
        int $busNumber,
        Segment $segment
    ): void {
        $prefix = 'WLEDBus' . $busNumber;
        $color = $segment->getPrimaryColor();

        $red = (int) ($color[0] ?? 0);
        $green = (int) ($color[1] ?? 0);
        $blue = (int) ($color[2] ?? 0);
        $white = (int) ($color[3] ?? 0);

        $this->writeValues([
            $prefix . 'Power'      => $segment->isOn(),
            $prefix . 'Brightness' => $segment->getBrightness(),
            $prefix . 'Color'      => $this->packRgb(
                $red,
                $green,
                $blue
            ),
            $prefix . 'White'      => $white,
            $prefix . 'Effect'     => $segment->getEffect()
        ]);
    }

    private function resetWLEDControlSegment(
        int $busNumber
    ): void {
        $prefix = 'WLEDBus' . $busNumber;

        $this->writeValues([
            $prefix . 'Power'      => false,
            $prefix . 'Brightness' => 0,
            $prefix . 'Color'      => 0,
            $prefix . 'White'      => 0,
            $prefix . 'Effect'     => 0
        ]);
    }

    private function packRgb(
        int $red,
        int $green,
        int $blue
    ): int {
        return
            (($red & 0xFF) << 16)
            | (($green & 0xFF) << 8)
            | ($blue & 0xFF);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function writeValues(array $values): void
    {
        foreach ($values as $ident => $value) {
            ($this->valueWriter)($ident, $value);
        }
    }
}