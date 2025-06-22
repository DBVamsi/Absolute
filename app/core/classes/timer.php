<?php
/**
 * A simple class to measure and output the execution time between its instantiation and destruction.
 * Note: Echoing directly in __destruct() can sometimes be problematic depending on when the object is destroyed.
 */
  class Timer
  {
    /** @var int|null Timestamp when the timer was started. */
    private $Time_Started = null;

    /**
     * Constructor for Timer.
     * Initializes the start time.
     */
    public function __construct()
    {
      $this->Time_Started = time();
    }

    /**
     * Destructor for Timer.
     * Outputs the time elapsed since the timer was started.
     * Note: Direct output in a destructor can have side effects if output buffering is involved or headers are already sent.
     * Consider returning the value or logging it instead for more flexibility.
     */
    public function __destruct()
    {
      if ($this->Time_Started !== null)
      {
        $time_elapsed = time() - $this->Time_Started;
        // Output is not escaped as it's developer information and not user input.
        echo "Timer finished in " . $time_elapsed . " seconds";
      }
    }
  }
