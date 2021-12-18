<?php

namespace App\Command;

use App\Model\TramwayStop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NextTramwayCommand extends Command
{
    protected static $defaultName = 'app:next-tramway';
    protected static $defaultDescription = 'Give the time remaining before the next tramway';

    private const FILE_URL = 'https://data.montpellier3m.fr/sites/default/files/ressources/TAM_MMM_TpsReel.csv';
    private const MY_TRAMWAY_STATION_ID_TO_THE_CENTER = 'PMARIRTW';
    private const MY_TRAMWAY_STATION_ID_TO_THE_OUTSIDE = 'PMARIATW';
    private const TRAMWAY_LINE_ONE = "1";
    private const TRAMWAY_LINE_THREE = "3";

    private const TRAMWAY_LINES = [self::TRAMWAY_LINE_ONE, self::TRAMWAY_LINE_THREE];
    private const MY_TRAMWAY_STATION_IDS = [self::MY_TRAMWAY_STATION_ID_TO_THE_CENTER, self::MY_TRAMWAY_STATION_ID_TO_THE_OUTSIDE];

    private HttpClientInterface $client;
    private SerializerInterface $serializer;

    public function __construct(HttpClientInterface $client, SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
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
        $response = $this->client->request('GET', self::FILE_URL);
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            $io->error('❌ Cannot fetch the tramway stops, got status code '.$response->getStatusCode());

            return Command::FAILURE;
        }
        $content = $response->getContent();
        $tramwayStopsAsArray = $this->serializer->decode($content, 'csv', [
            'csv_delimiter' => ';',
        ]);
        /**
        $tramwayStops = $this->serializer->deserialize($content, TramwayStop::class, 'csv', [
            'csv_delimiter' => ';',
        ]);
        */

        $tramwayStopsAtMyStation = array_filter($tramwayStopsAsArray, fn (array $tramwayStop) => isset($tramwayStop['stop_code']) && in_array($tramwayStop['stop_code'], self::MY_TRAMWAY_STATION_IDS));
        $line = $input->getOption('line');
        if ($line) {
            $this->displayTramwayStopsForLine($io, $tramwayStopsAtMyStation, $line, $input->getOption('direction'));

            return Command::SUCCESS;
        }

        foreach (self::TRAMWAY_LINES as $line) {
            $this->displayTramwayStopsForLine($io, $tramwayStopsAtMyStation, $line, $input->getOption('direction'));
        }

        return Command::SUCCESS;
    }

    private function displayTramwayStopsForLine(SymfonyStyle $io, array $tramwayStops, string $line, ?int $direction = null): void
    {
        $tramwayStopsForThisLine = array_filter($tramwayStops, fn (array $tramwayStop) => $line === $tramwayStop['route_short_name']);

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
        $tramwayStopsForThisDirection = array_filter($tramwayStops, fn (array $tramwayStop) => self::MY_TRAMWAY_STATION_IDS[$direction] === $tramwayStop['stop_code']);
        $io->info('Direction '.((0 === $direction) ? 'le centre 🏙' : 'l\'extérieur 🏖'));
        $this->sortByWaitingTimeAsc($tramwayStopsForThisDirection);
        $output = array_map(fn (array $tramwayStop) => '🚈 Destination '.$tramwayStop['trip_headsign'].', arrivée dans '. floor($tramwayStop['delay_sec']/60).' minutes.', $tramwayStopsForThisDirection);
        $io->listing($output);
    }

    private function sortByWaitingTimeAsc(array &$tramwayStops): void
    {
        $waitingTimes = [];
        foreach ($tramwayStops as $key => $tramwayStop)
        {
            $waitingTimes[$key] = $tramwayStop['delay_sec'];
        }
        array_multisort($waitingTimes, SORT_ASC, $tramwayStops);
    }
}
