<?php

namespace App\Command;

use App\Model\TramwayStop;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NextTramwayCommand extends Command
{
    protected static $defaultName = 'app:next-tramway';
    protected static $defaultDescription = 'Give the time remaining before the next tramway';

    private const FILE_URL = 'https://data.montpellier3m.fr/sites/default/files/ressources/TAM_MMM_TpsReel.csv';
    private const MY_TRAMWAY_STATION_ID_TO_THE_CENTER = 'PMARIRTW';
    private const MY_TRAMWAY_STATION_ID_TO_THE_OUTSIDE = 'PMARIATW';
    private const TRAMWAY_LINE_ONE = 1;
    private const TRAMWAY_LINE_THREE = 3;

    private const TRAMWAY_LINES = [self::TRAMWAY_LINE_ONE, self::TRAMWAY_LINE_THREE];
    private const MY_TRAMWAY_STATION_IDS = [self::MY_TRAMWAY_STATION_ID_TO_THE_CENTER, self::MY_TRAMWAY_STATION_ID_TO_THE_OUTSIDE];

    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('line', 'l', InputOption::VALUE_OPTIONAL, 'line')
            ->addOption('direction', 'd', InputOption::VALUE_OPTIONAL, 'direction')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('✉️  Fetching realtime data from TAM API');
        try {
            $tramwayStops = $this->fetchTramwayStops();
        } catch (Exception $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $tramwayStopsAtMyStation = array_filter($tramwayStops, fn (TramwayStop $tramwayStop) => $this->isInMyTramwayStation($tramwayStop));
        $linesToConsider = self::TRAMWAY_LINES;
        $lineOption = intval($input->getOption('line'));
        $linesToConsider = array_filter(self::TRAMWAY_LINES, fn (int $line) => !$lineOption || $lineOption === $line);

        foreach ($linesToConsider as $line) {
            $this->displayTramwayStopsForLine($io, $tramwayStopsAtMyStation, $line, $input->getOption('direction'));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<TramwayStop> $tramwayStops
     */
    private function fetchTramwayStops(): array
    {
        $response = $this->client->request('GET', self::FILE_URL);
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new Exception('❌ Cannot fetch the tramway stops, got status code '.$response->getStatusCode());
        }
        $content = $response->getContent();

        $serializer = new Serializer(
            [new GetSetMethodNormalizer(null, new CamelCaseToSnakeCaseNameConverter()), new ArrayDenormalizer()],
            [new CsvEncoder()]
        );
        return $serializer->deserialize($content, TramwayStop::class . '[]', 'csv', [
            'csv_delimiter' => ';',
        ]);
    }

    private function isInMyTramwayStation(TramwayStop $tramwayStop): bool
    {
        return (null !== $tramwayStop->getStopCode()) && in_array($tramwayStop->getStopCode(), self::MY_TRAMWAY_STATION_IDS);
    }

    private function displayTramwayStopsForLine(SymfonyStyle $io, array $tramwayStops, int $line, ?int $direction = null): void
    {
        $tramwayStopsForThisLine = array_filter($tramwayStops, fn (TramwayStop $tramwayStop) => $this->isTramwayStopInLine($tramwayStop, $line));

        if (!count($tramwayStopsForThisLine)) {
            return;
        }
        $io->info('Prochains trams pour la ligne '. $line);
        if (null !== $direction) {
            $this->displayTramwayStopsForLineAndDirection($io, $tramwayStopsForThisLine, $direction);

            return;
        }
        $this->displayTramwayStopsForLineAndDirection($io, $tramwayStopsForThisLine, 0);
        $this->displayTramwayStopsForLineAndDirection($io, $tramwayStopsForThisLine, 1);
    }

    private function displayTramwayStopsForLineAndDirection(SymfonyStyle $io, array $tramwayStops, int $direction): void
    {
        $tramwayStopsForThisDirection = array_filter($tramwayStops, fn (TramwayStop $tramwayStop) => $this->isTramwayStopInDirection($tramwayStop, $direction));
        $io->info('Direction '.((0 === $direction) ? 'le centre 🏙' : 'l\'extérieur 🏖'));
        $this->sortByWaitingTimeAsc($tramwayStopsForThisDirection);
        $output = array_map(fn (TramwayStop $tramwayStop) => $this->formatTramwayStopToString($tramwayStop), $tramwayStopsForThisDirection);
        $io->listing($output);
    }

    private function isTramwayStopInLine(TramwayStop $tramwayStop, int $line): bool
    {
        return $line === $tramwayStop->getRouteShortName();
    }

    private function isTramwayStopInDirection(TramwayStop $tramwayStop, int $direction): bool
    {
        return self::MY_TRAMWAY_STATION_IDS[$direction] === $tramwayStop->getStopCode();
    }

    private function formatTramwayStopToString(TramwayStop $tramwayStop): string
    {
        return '🚈 Destination '.$tramwayStop->getTripHeadsign().', arrivée dans <fg=yellow;options=bold>'. floor($tramwayStop->getDelaySec()/60).'</> minutes.';
    }

    private function sortByWaitingTimeAsc(array &$tramwayStops): void
    {
        $waitingTimes = [];
        /** @var TramwayStop $tramwayStop */
        foreach ($tramwayStops as $key => $tramwayStop)
        {
            $waitingTimes[$key] = $tramwayStop->getDelaySec();
        }
        array_multisort($waitingTimes, SORT_ASC, $tramwayStops);
    }
}
