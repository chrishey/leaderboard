<?php

/*
	Copyright (c) 2012 Colin Rotherham, http://colinr.com
	https://github.com/colinrotherham
*/

	namespace CRD\Leaderboard;

	class GameRankings
	{
		public $results = array();

		private $results_wins;
		private $results_losses;

		private $database;

		public function __construct()
		{
			$this->database = new \CRD\Core\Database();
			$this->database->Connect();

			$this->query();

			// Continue if not empty
			if (!empty($this->results_wins->num_rows) && !empty($this->results_losses->num_rows))
			{
				$this->rankings();
			}
		}

		private function query()
		{
			// Optional WHERE clause when narrowing to current week
			$where_clause = (SHOW_ALL)? '' : \CRD\Core\App::$queries->clause_week;

			// Database results
			$this->results_wins = $this->database->Query(sprintf(\CRD\Core\App::$queries->wins, $where_clause));
			$this->results_losses = $this->database->Query(sprintf(\CRD\Core\App::$queries->losses, $where_clause));
		}

		public function rankings()
		{
			// Build up results objects
			while ($win = $this->results_wins->fetch_object())
			{
				$result = new GameScore($win->name);
				$result->scores($win->wins, 0);

				// Add to array
				$this->results[$win->id] = $result;
			}

			// Append losses
			while ($loss = $this->results_losses->fetch_object())
			{
				// Ignore if this person has never won a game
				if (empty($this->results[$loss->id]))
					continue;

				$result = $this->results[$loss->id];
				$result->scores($result->wins, $loss->losses);
			}
			
			// Sort object by wins
			usort($this->results, array($this, 'sort_wins_losses'));

			// Process standings with differential and games-behind
			$this->standings();
		}

		public function standings()
		{
			if (count($this->results) > 0)
			{
				// Determine top players wins/losses for games-behind
				$lead_wins = $this->results[0]->wins;
				$lead_losses = $this->results[0]->losses;

				// Loops results object, calculate differential
				foreach ($this->results as $id => $result)
				{
					$result->standing($lead_wins, $lead_losses);
				}

				// Determind rank by differential, games behind
				usort($this->results, array($this, 'sort_rank'));
			}
		}

		public function sort_wins_losses($a, $b)
		{
			return ($a->wins - $a->losses) < ($b->wins - $b->losses);
		}

		public function sort_rank($a, $b)
		{
			return ($a->differential - $a->games_behind) < ($b->differential - $b->games_behind);
		}
	}
?>