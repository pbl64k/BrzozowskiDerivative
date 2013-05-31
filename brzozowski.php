<?php

	error_reporting(E_ALL | E_STRICT);

	interface IBdParserInput
	{
		public function isEmpty();
		public function getFirst();
		public function getRest();
	}

	final class SimpleStringBdParserInput implements IBdParserInput
	{
		private $string;

		final public static function make($string)
		{
			return self::makeRest($string, 0);
		}

		final public function isEmpty()
		{
			return $this->ix === strlen($this->string);
		}

		final public function getFirst()
		{
			return substr($this->string, $this->ix, 1);
		}

		final public function getRest()
		{
			return self::makeRest($this->string, $this->ix + 1);
		}

		final private static function makeRest(&$string, $ix = 0)
		{
			return new self($string, $ix);
		}

		final private function __construct(&$string, $ix)
		{
			$this->string =& $string;
			$this->ix = $ix;
		}
	}

	interface IIdentityObject
	{
		/*
		public function getId();
		public function getTrueId();
		*/
	}

	interface IBdParser extends IIdentityObject
	{
		public function parse(IBdParserInput $input);
		public function isNullable();
		public function delta();
		public function derive($token);
		public function isEmptyParser();
	}

	abstract class AbstractIdentityObject
	{
		/*
		private $id;

		public function getId()
		{
			return $this->id;
		}

		final public function getTrueId()
		{
			return $this->getId();
		}

		protected function __construct()
		{
			$this->id = uniqid();
		}
		*/

		protected function __construct()
		{
		}
	}

	$promiseCount = 0;

	final class BdParserPromise extends AbstractIdentityObject implements IBdParser
	{
		static private $emptyParser = NULL;
		static private $epsilonParser = NULL;

		private $parser = NULL;
		private $promise;

		final public static function make(Closure $parserPromise)
		{
			global $promiseCount;

			++$promiseCount;

			return new self($parserPromise);
		}

		final public static function makeEmpty()
		{
			if (is_null(self::$emptyParser))
			{
				self::$emptyParser = self::make(function() { return BdParserEmpty::make(); });
			}

			return self::$emptyParser;
		}

		final public static function makeEpsilon()
		{
			if (is_null(self::$epsilonParser))
			{
				self::$epsilonParser = self::make(function() { return BdParserEpsilon::make(); });
			}

			return self::$epsilonParser;
		}

		final public function parse(IBdParserInput $input)
		{
			return $this->getParser()->parse($input);
		}

		final public function isNullable()
		{
			return $this->getParser()->isNullable();
		}

		final public function delta()
		{
			return $this->getParser()->delta();
		}

		final public function derive($token)
		{
			return $this->getParser()->derive($token);
		}

		final public function isEmptyParser()
		{
			return $this->getParser()->isEmptyParser();
		}

		final public function getId()
		{
			return $this->getParser()->getId();
		}

		final public function getParser()
		{
			if (is_null($this->parser))
			{
				$parserPromise = $this->promise;

				$this->parser = $parserPromise();
			}

			return $this->parser;
		}

		final protected function __construct($parserPromise)
		{
			parent::__construct();

			$this->promise = $parserPromise;
		}
	}

	abstract class AbstractBdParser extends AbstractIdentityObject
	{
		static private $deltas = NULL;

		abstract public function isNullable();
		abstract public function derive($token);

		final public function delta()
		{
			if (is_null(self::$deltas))
			{
				self::$deltas[0] = BdParserPromise::makeEmpty();
				self::$deltas[1] = BdParserPromise::makeEpsilon();
			}

			return self::$deltas[$this->isNullable() ? 1 : 0];
		}

		final public function parse(IBdParserInput $input)
		{
			$parser = $this;

			while (! $input->isEmpty())
			{
				$token = $input->getFirst();
				$input = $input->getRest();

				$parser = $parser->derive($token);
			}

			return $parser->isNullable();
		}

		public function isEmptyParser()
		{
			return FALSE;
		}

		protected function __construct()
		{
			parent::__construct();
		}
	}

	abstract class AbstractCompositeBdParser extends AbstractBdParser
	{
		private $nullable = NULL;

		private $derivs = array();

		private $lp;
		private $rp;

		abstract public function innerIsNullable();
		abstract public function innerDerive($token);

		final public function isNullable()
		{
			if (is_null($this->nullable))
			{
				$this->nullable = FALSE;
			
				do
				{
					$this->nullable = $this->innerIsNullable();
				}
				while ($this->nullable !== $this->innerIsNullable());
			}

			return $this->nullable;
		}

		final public function derive($token)
		{
			if (! isset($this->derivs[$token]))
			{
				$self = $this;

				$this->derivs[$token] =
						BdParserPromise::make(
								function() use(&$self, $token)
								{
									return $self->innerDerive($token);
								});
			}

			return $this->derivs[$token];
		}

		final public function getLp()
		{
			return $this->lp;
		}

		final public function getRp()
		{
			return $this->rp;
		}

		final protected function __construct(BdParserPromise $lp, BdParserPromise $rp)
		{
			parent::__construct();

			$this->lp = $lp;
			$this->rp = $rp;
		}
	}

	final class BdParserEpsilon extends AbstractBdParser implements IBdParser
	{
		static private $inst = NULL;

		static private $deriv = NULL;

		final public static function make()
		{
			if (is_null(self::$inst))
			{
				self::$inst = new self;

				self::$deriv = BdParserPromise::makeEmpty();
			}

			return self::$inst;
		}

		final public function isNullable()
		{
			return TRUE;
		}

		final public function derive($token)
		{
			return self::$deriv;
		}

		final protected function __construct()
		{
			parent::__construct();
		}
	}

	final class BdParserEmpty extends AbstractBdParser implements IBdParser
	{
		static private $inst = NULL;

		final public static function make()
		{
			if (is_null(self::$inst))
			{
				self::$inst = new self;
			}

			return self::$inst;
		}

		final public function isNullable()
		{
			return FALSE;
		}

		final public function derive($token)
		{
			return $this;
		}

		final public function isEmptyParser()
		{
			return TRUE;
		}

		final protected function __construct()
		{
			parent::__construct();
		}
	}

	final class BdParserLiteral extends AbstractBdParser implements IBdParser
	{
		static private $insts = array();

		static private $derivs = NULL;

		public $token;

		final public static function make($token)
		{
			if (is_null(self::$derivs))
			{
				self::$derivs[0] = BdParserPromise::makeEmpty();
				self::$derivs[1] = BdParserPromise::makeEpsilon();
			}

			if (! isset(self::$insts[$token]))
			{
				self::$insts[$token] = new self($token);
			}

			return self::$insts[$token];
		}

		final public function isNullable()
		{
			return FALSE;
		}

		final public function derive($token)
		{
			return self::$derivs[($this->token === $token) ? 1 : 0];
		}

		final protected function __construct($token)
		{
			parent::__construct();

			$this->token = $token;
		}
	}

	final class BdParserUnion extends AbstractCompositeBdParser implements IBdParser
	{
		final public static function make(BdParserPromise $lp, BdParserPromise $rp)
		{
			return new self($lp, $rp);
		}

		final public function innerIsNullable()
		{
			return $this->getLp()->isNullable() || $this->getRp()->isNullable();
		}

		final public function innerDerive($token)
		{
			if ($this->getLp()->isEmptyParser() && $this->getRp()->isEmptyParser())
			{
				return BdParserEmpty::make();
			}
			elseif ($this->getLp()->isEmptyParser())
			{
				return $this->getRp()->derive($token);
			}
			elseif ($this->getRp()->isEmptyParser())
			{
				return $this->getLp()->derive($token);
			}
			else
			{
				$self = $this;

				return self::make(
						BdParserPromise::make(function() use(&$self, $token) { return $self->getLp()->derive($token); }),
						BdParserPromise::make(function() use(&$self, $token) { return $self->getRp()->derive($token); }));
			}
		}
	}

	final class BdParserConcat extends AbstractCompositeBdParser implements IBdParser
	{
		final public static function make(BdParserPromise $lp, BdParserPromise $rp)
		{
			return new self($lp, $rp);
		}

		final public function innerIsNullable()
		{
			return $this->getLp()->isNullable() && $this->getRp()->isNullable();
		}

		final public function innerDerive($token)
		{
			$self = $this;

			if ($this->getLp()->isEmptyParser() || $this->getRp()->isEmptyParser())
			{
				return BdParserEmpty::make();
			}

			return  BdParserUnion::make(
					BdParserPromise::make(
							function() use(&$self, $token)
							{
								return BdParserConcat::make(
										BdParserPromise::make(
												function() use(&$self, $token)
												{
													return $self->getLp()->derive($token);
												}),
										BdParserPromise::make(
												function() use(&$self)
												{
													return $self->getRp();
												}));
							}),
					BdParserPromise::make(
							function() use(&$self, $token)
							{
								return BdParserConcat::make(
										BdParserPromise::make(
												function() use(&$self)
												{
													return $self->getLp()->delta();
												}),
										BdParserPromise::make(
												function() use(&$self, $token)
												{
													return $self->getRp()->derive($token);
												}));
							}));
		}
	}

	function test($val, $exp)
	{
		$bt = debug_backtrace();
		$line = $bt[0]['line'];

		print('Line '.$line.' test: ');

		if ($val === $exp)
		{
			print('OK.'."\n");
		}
		else
		{
			print('FAILED -- expected {'.$exp.'}, got {'.$val.'}'."\n");
		}
	}

	/*
	*/
	$exp = BdParserPromise::makeEmpty();

	$exp = BdParserPromise::make(
			function() use(&$exp)
			{
				return BdParserUnion::make(
						BdParserPromise::make(
								function() use(&$exp)
								{
										return BdParserConcat::make($exp,
												BdParserPromise::make(function() { return BdParserLiteral::make('.'); }));
								}),
						BdParserPromise::makeEpsilon());
			});

	//test($exp->parse(SimpleStringBdParserInput::make('')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make('.')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make('...............')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make(',')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make(',.')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('.,')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make(',,,')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('.,.')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make(',,.....,,......,,')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make(str_repeat('.', 100))), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make(','.str_repeat('.', 100))), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('.,'.str_repeat('.', 100))), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make(str_repeat('.', 100).',')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make(str_repeat('.', 100).',.')), FALSE);
	/*
	*/

	/*
	*/
	$exp = BdParserPromise::makeEmpty();

	$exp =
			BdParserPromise::make(function() use(&$exp)
			{
				return BdParserUnion::make(
						BdParserPromise::make(function() use(&$exp)
						{
								return BdParserConcat::make($exp,
										BdParserPromise::make(function() use(&$exp)
										{
											return BdParserConcat::make(BdParserPromise::make(function() { return BdParserLiteral::make('('); }),
													BdParserPromise::make(function() use(&$exp)
													{
														return BdParserConcat::make($exp, BdParserPromise::make(function() { return BdParserLiteral::make(')'); }));
													}));
										}));
						}),
						BdParserPromise::makeEpsilon());
			});

	//test($exp->parse(SimpleStringBdParserInput::make('()')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make(')')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('((')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('(')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('(()')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('(())')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make('(())()')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make('')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make('((()()))')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make('((()))(())()')), TRUE);
	//test($exp->parse(SimpleStringBdParserInput::make('((()))(())())')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('(((()))(())()')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('((()))((())()')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('((())))(())()')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('(')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('())')), FALSE);
	//test($exp->parse(SimpleStringBdParserInput::make('(()()')), FALSE);
	test($exp->parse(SimpleStringBdParserInput::make(str_repeat('()', 100))), TRUE);
	print('promises constructed: '.$promiseCount."\n");
	die();
	test($exp->parse(SimpleStringBdParserInput::make('('.str_repeat('()', 100))), FALSE);
	test($exp->parse(SimpleStringBdParserInput::make(str_repeat('()', 100).')')), FALSE);
	test($exp->parse(SimpleStringBdParserInput::make('('.str_repeat('()', 100).')')), TRUE);
	/*
	*/

	function eps()
	{
		return BdParserPromise::makeEpsilon();
	}

	function lit($token)
	{
		return BdParserPromise::make(function() use($token) { return BdParserLiteral::make($token); });
	}

	function choice(array $tokens)
	{
		if (count($tokens) === 1)
		{
			return lit($tokens[0]);
		}
		else
		{
			$token = array_pop($tokens);

			return BdParserPromise::make(
					function() use($token, $tokens)
					{
						return BdParserUnion::make(lit($token), choice($tokens));
					});
		}
	}

	$exps = array();

	$exps['space'] = lit(' ');
	$exps['lparen'] = lit('(');
	$exps['rparen'] = lit(')');
	$exps['lambda'] = lit('\\');
	$exps['dot'] = lit('.');

	$exps['spaces'] = BdParserPromise::make(
			function() use(&$exps)
			{
				return BdParserUnion::make(
						BdParserPromise::make(
								function() use(&$exps)
								{
									return BdParserConcat::make($exps['spaces'], $exps['space']);
								}),
						eps());
			});

	$exps['var'] = choice(str_split('abcdefghijklmnopqrstuvwxyz', 1));
	$exps['atom'] = choice(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ', 1));

	test($exps['var']->parse(SimpleStringBdParserInput::make('W')), FALSE);
	test($exps['var']->parse(SimpleStringBdParserInput::make('u')), TRUE);
	test($exps['atom']->parse(SimpleStringBdParserInput::make('W')), TRUE);
	test($exps['atom']->parse(SimpleStringBdParserInput::make('u')), FALSE);

	print(memory_get_usage()."\n");

	print('promises constructed: '.$promiseCount."\n");

?>
