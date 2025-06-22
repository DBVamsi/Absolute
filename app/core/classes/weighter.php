<?php
/**
 * A utility class for performing weighted random selections.
 * Objects (or their descriptions/keys) are added with a specific weight,
 * and GetObject() will return one of the added objects based on these weights.
 */
  class Weighter
  {
    /** @var array Stores the objects and their cumulative weights. */
    protected array $Objects = [];
    /** @var int|float Total weight of all objects added. */
    protected int|float $Total_Weight = 0;

    /**
     * Adds an object (or its identifier) to the selection pool with a specific weight.
     *
     * @param mixed $Description The object or its identifier/description to be returned by GetObject().
     * @param int|float $Weight_Value The weight for this object. Higher values increase its chance of being selected. Must be positive.
     * @return void
     */
    public function AddObject(mixed $Description, int|float $Weight_Value): void
    {
      if ($Weight_Value <= 0) {
        // Optionally throw an error or log, but for now, just ignore non-positive weights.
        return;
      }

      $this->Total_Weight += $Weight_Value;

      $this->Objects[] = [
        'Description' => $Description, // The actual item/key to return
        'Weight' => $Weight_Value,     // Individual weight of this item
        'Cumulative_Weight' => $this->Total_Weight // Upper bound for this item in random selection
      ];
    }

    /**
     * Selects an object from the pool based on the defined weights.
     *
     * @return mixed|false The description/identifier of the selected object, or false if no objects were added.
     */
    public function GetObject(): mixed
    {
      if ( empty($this->Objects) || $this->Total_Weight <= 0 ) {
        return false;
      }

      $Random_Weight = mt_rand(1, (int)$this->Total_Weight); // mt_rand expects integers

      foreach ( $this->Objects as $Object )
      {
        // If the random number falls within the cumulative weight range of this object
        if ( $Random_Weight <= $Object['Cumulative_Weight'] )
        {
          return $Object['Description'];
        }
      }

      // Should ideally not be reached if Objects is not empty and Total_Weight > 0,
      // but as a fallback, return the last object's description (or false if list somehow empty).
      // This could happen if mt_rand produced a value equal to Total_Weight and the loop condition
      // was such that it didn't catch the last item. The current logic should catch it.
      return !empty($this->Objects) ? end($this->Objects)['Description'] : false;
    }
  }
