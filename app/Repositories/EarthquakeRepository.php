<?php

namespace App\Repositories;

use App\LastTwentyRecord;
use App\Traits\UtilityTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;
use Str;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

class EarthquakeRepository
{
    use UtilityTrait;

    private $minMagnitude = 4.5;

    public function init()
    {
        $tableFirst20RowsArray = $this->getTableStringAndStoreEachRowInArrayAndReturnFirst20();
        $matchKey = $this->checkIfNewRecordsExistAndReturnCountOfThem($tableFirst20RowsArray);

        if ($matchKey === 0) {
            return 'phew that was close';
        }

        $newRecords = $this->refreshRecordsInDBAndReturnOnlyNewRecords($tableFirst20RowsArray, $matchKey);
        try {
            $this->sendSMSIfMagnitudeBiggerThanExpected($newRecords);
        } catch (ConfigurationException $e) {
            // todo enhance
        } catch (TwilioException $e) {
            // todo enhance
        }
    }


    /**
     * Return the raw html response.
     *
     * @return string
     * @throws GuzzleException
     */
    private function fetchData(): string
    {
        $client = new Client();

        try {
            $response = $client->get('http://www.koeri.boun.edu.tr/scripts/lst2.asp');
        } catch (GuzzleException $ex) {
            $this->fetchData();
        }
        return $response->getBody()->getContents();
    }


    /**
     * Since fetchData() returns the whole UI
     * we need to isolate the table in the middle
     * and store them as array elements to treat them as 'records'.
     *
     * @throws GuzzleException
     */
    private function getTableStringAndStoreEachRowInArrayAndReturnFirst20(): array
    {
        $rawHtmlString = $this->fetchData();

        $startingPos = '---------- --------  --------  -------   ----------    ------------    --------------                                  --------------';
        $endingPos = '</pre>';
        $tableString = $this->stringBetween($rawHtmlString, $startingPos, $endingPos);
        $tableArray = explode("\r\n", $tableString);

        // todo: enhance the string between to exclude this
        unset($tableArray[0]);
        return array_slice(array_values($tableArray), 0, 20);

    }

    /**
     * Compare new records fro api with existing in db and return
     * a number of differences that represent the new records
     *
     * @param $tableArrayFirst20Items
     * @return string|null
     */
    private function checkIfNewRecordsExistAndReturnCountOfThem($tableArrayFirst20Items): ?string
    {
        $records = LastTwentyRecord::all();

        if (count($records) === 0) {
            // first hit
            $this->createRecords($tableArrayFirst20Items);
        }

        $lastRecord = LastTwentyRecord::orderBy('id', 'asc')->first();
        $matchString = $lastRecord->time . '  ' . $lastRecord->lat . '   ' . $lastRecord->long;
        return $this->returnKeyFromArrayElementThatContainsGivenString($tableArrayFirst20Items, $matchString);
    }

    /**
     * Insert first 20 records
     *
     * @param $tableArrayFirst20Items
     * @param $matchedKey
     * @return LastTwentyRecord[]|Collection
     */
    private function refreshRecordsInDBAndReturnOnlyNewRecords($tableArrayFirst20Items, $matchedKey)
    {
        // to make sure we only keep first 20 records each time we hit API
        LastTwentyRecord::truncate();

        $this->createRecords($tableArrayFirst20Items);
        return LastTwentyRecord::all()->take($matchedKey);
    }


    /**
     * Message all subscribed numbers the record
     * if the magnitude is bigger than the set value ($this->minMagnitude)
     *
     * @param $newRecords
     * @throws ConfigurationException
     * @throws TwilioException
     */
    private function sendSMSIfMagnitudeBiggerThanExpected($newRecords): void
    {
        $phones = config('services.numbers');

        foreach ($newRecords as $new) {
            if ((float)$new->magnitude > $this->minMagnitude) {

                foreach ($phones as $phone) {
                    $from = config('services.twilio.whatsapp_from');

                    $twilio = new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));
                    $twilio->messages->create($phone->phone_number, [
                        "from" => $from,
                        "body" => $this->prepareAndReturnMessageString($new)
                    ]);
                }
            }
        }
    }

    /**
     * Create the records. Positions are hardcoded because
     * when we break down the table row, values that we need always
     * reside in those positions to keep table view intact on UI.
     *
     * @param array $tableArrayFirst20Items
     */
    private function createRecords(array $tableArrayFirst20Items): void
    {
        foreach ($tableArrayFirst20Items as $record) {
            $record = array_values(array_filter(explode(" ", $record)));
            LastTwentyRecord::create([
                'time' => $record[0] . ' ' . $record[1],
                'lat' => $record[2],
                'long' => $record[3],
                'magnitude' => $record[6],
                'place' => $this->getLocationName($record),
            ]);
        }
    }

    /**
     * Before storing each record, make sure it only contains
     * the place name.
     *
     * We know that when we explode one row, the place string starts
     * at the position 8 always. But the name could be long, ending up in
     * other positions. So we're basically making a string from arr[8] and
     * above.
     *
     * @param $record
     * @return string
     */
    public function getLocationName($record): string
    {
        $locationName = '';
        for ($i = 8, $iMax = count($record); $i < $iMax; $i++) {
            if (!Str::contains($record[$i], ['REVIZE', 'lksel', '.' ,':'])) {
                $locationName .= ' ' . $record[$i];
            }
        }
        return $locationName;
    }

    /**
     * Generate the SMS body.
     *
     * @param LastTwentyRecord $new
     * @return string
     */
    private function prepareAndReturnMessageString(LastTwentyRecord $new): string
    {
        return "Earthquake in Turkey: at {$new->time}, around {$new->place}, in magnitude of {$new->magnitude}. Show in map: https://www.google.com/maps/place/{$new->lat}%20{$new->long}";
    }

}
