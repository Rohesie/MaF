<?php

namespace BM2\SiteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;


class ProcessBattlesCommand extends ContainerAwareCommand {

	protected function configure() {
		$this
			->setName('maf:process:battles')
			->setDescription('Process all pending battles.')
			->addArgument('debug level', InputArgument::OPTIONAL, 'debug level')
			->addOption('time', 't', InputOption::VALUE_NONE, 'output timing information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$container = $this->getContainer();
		$em = $container->get('doctrine')->getManager();
		$logger = $container->get('logger');
		$battlerunner = $container->get('battle_runner');
		$warmanager = $container->get('war_manager');
		$cycle = $container->get('appstate')->getCycle();
		$opt_time = $input->getOption('time');
		$arg_debug = $input->getArgument('debug level');
		$appstate = $container->get('appstate');

		if ($appstate->getGlobal('battling') == 0) {
			$logger->info("battles: starting...");
			$appstate->setGlobal('battling', 1);

			$stopwatch = new Stopwatch();
			$stopwatch->start('battles');

			$now = new \DateTime("now");

			// recalculate battle timers for battles I'm about to resolve to fix various trickery
			$query = $em->createQuery('SELECT b FROM BM2SiteBundle:Battle b WHERE b.complete < :now ORDER BY b.id ASC');
			$query->setParameters(array('now'=>$now));
			foreach ($query->getResult() as $battle) {
				$warmanager->recalculateBattleTimer($battle);
			}
			$em->flush();

			$query = $em->createQuery('SELECT b FROM BM2SiteBundle:Battle b WHERE b.complete < :now ORDER BY b.id ASC');
			$query->setParameters(array('now'=>$now));
			foreach ($query->getResult() as $battle) {
				$battlerunner->enableLog($arg_debug);
				$battlerunner->run($battle, $cycle);
			}
			if ($opt_time) {
				$event = $stopwatch->lap('battles');
				$logger->info("battles: computation timing ".date("g:i:s").", ".($event->getDuration()/1000)." s, ".(round($event->getMemory()/1024)/1024)." MB");
			}
			$logger->info("battles: ...flushing...");
			$em->flush();
			if ($opt_time) {
				$event = $stopwatch->stop('battles');
				$logger->info("battles: flush data timing ".date("g:i:s").", ".($event->getDuration()/1000)." s, ".(round($event->getMemory()/1024)/1024)." MB");
			}
			$appstate->setGlobal('battling', 0);
			$logger->info("battles: ...complete");
		} else {
			$logger->info("battles: additional running prevented");
		}
	}
}
