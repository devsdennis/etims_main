<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Product;
use App\Contact;
use App\Transaction;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class EtimsController extends Controller
{

    public function handleCallback(Request $request)
    {
        \Log::info('eTIMS Callback Response', [
            'payload' => $request->all()
        ]);

        return response()->json(['status' => 'success']);
    }
    //private const API_KEY = 'api_key_wlSn2XkoMUzvSPYWFEe0LBasFeo5s3y6';
    //private const BUSINESS_ID = 'clpwnlshq0001la08phdikdka';

    //Test papetrail connection
    public function testPapertrailConnection()
{
    $url = config('logging.channels.papertrail.handler_with.host');
    $port = config('logging.channels.papertrail.handler_with.port');

    // Detailed logging for debugging
    Log::info('Papertrail Connection Debug', [
        'Config URL' => $url,
        'Config PORT' => $port,
        'ENV URL' => env('PAPERTRAIL_URL'),
        'ENV PORT' => env('PAPERTRAIL_PORT')
    ]);

    try {
        // Use stream_socket_client for more reliable connection testing
        $errorNumber = 0;
        $errorMessage = '';
        
        $socket = stream_socket_client(
            "tcp://{$url}:{$port}", 
            $errorNumber, 
            $errorMessage, 
            5  // timeout in seconds
        );

        if ($socket) {
            // Successfully connected
            fclose($socket);
            Log::info('Papertrail Connection Successful');
            return true;
        } else {
            // Connection failed
            Log::error('Papertrail Connection Failed', [
                'URL' => $url,
                'PORT' => $port,
                'Error Number' => $errorNumber,
                'Error Message' => $errorMessage
            ]);
            return false;
        }
    } catch (\Exception $e) {
        Log::error('Papertrail Connection Exception', [
            'Message' => $e->getMessage(),
            'Trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

public function testPapertrailLogging() {
    try {
        // Attempt to log at different levels
        Log::channel('papertrail')->emergency('Emergency test log');
        Log::channel('papertrail')->alert('Alert test log');
        Log::channel('papertrail')->critical('Critical test log');
        Log::channel('papertrail')->error('Error test log');
        Log::channel('papertrail')->warning('Warning test log');
        Log::channel('papertrail')->notice('Notice test log');
        Log::channel('papertrail')->info('Info test log');
        Log::channel('papertrail')->debug('Debug test log');

        // Log with additional context
        Log::channel('papertrail')->info('Detailed Papertrail Test', [
            'timestamp' => now()->toDateTimeString(),
            'environment' => config('app.env'),
            'host' => gethostname(),
            'ip' => request()->ip()
        ]);

        // Prepare response data
        $responseData = [
            'status' => 'Successfully logged to Papertrail',
            'timestamp' => now()->toDateTimeString(),
            'levels_logged' => [
                'emergency', 'alert', 'critical', 'error', 
                'warning', 'notice', 'info', 'debug'
            ]
        ];

        // Log the response to the daily channel
        Log::channel('daily')->info('Papertrail Logging Test Response', $responseData);

        return response()->json($responseData);
    } catch (\Exception $e) {
        // Prepare error data
        $errorData = [
            'status' => 'Failed to log to Papertrail',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];

        // Log the error to the daily channel
        Log::channel('daily')->error('Papertrail Logging Test Failed', $errorData);

        // Detailed error logging to Papertrail
        Log::channel('papertrail')->error('Papertrail Logging Test Failed', $errorData);

        // Return error response
        return response()->json($errorData, 500);
    }
}


public function createCustomer(Request $request) {
    // Validate the request
    $validated = $request->validate([
        'customer_name' => 'required|string|max:255',
        'customer_pin' => 'required|string|max:50',
        'customer_email' => 'nullable|email|max:255',
        'customer_phone_number' => 'required|string|max:20'
    ]);

    try {
        // Retrieve all active business locations
        $businessLocations = BusinessLocation::where('is_active', 1)->get();

        // Group business locations by their API key to minimize API calls
        $apiKeyGroups = $businessLocations->groupBy('custom_field1');

        // Track successful and failed locations
        $successLocations = [];
        $failedLocations = [];

        // Create Guzzle HTTP client
        $client = new Client();

        // Iterate through API key groups
        foreach ($apiKeyGroups as $apiKey => $locations) {
            // Skip if no API key
            if (empty($apiKey)) {
                foreach ($locations as $location) {
                    $failedLocations[] = [
                        'location_id' => $location->id,
                        'error' => 'No API key provided'
                    ];
                }
                continue;
            }

            try {
                // Make a single API call for each unique API key group
                $response = $client->post('https://api.digitax.tech/branches/customer', [
                    'json' => [
                        'customer_name' => $request->customer_name,
                        'customer_tin' => $request->customer_pin,
                        'customer_email' => $request->customer_email,
                        'customer_phone_number' => $request->customer_phone_number
                    ],
                    'headers' => [
                        'X-API-Key' => $apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ]
                ]);

                // Parse the response
                $responseBody = json_decode($response->getBody(), true);

                // Mark all locations with this API key as successful
                foreach ($locations as $location) {
                    $successLocations[] = [
                        'location_id' => $location->id,
                        'response' => $responseBody
                    ];
                }

            } catch (\GuzzleHttp\Exception\ClientException $e) {
                // API call failed for this group of locations
                $responseBody = json_decode($e->getResponse()->getBody(), true);

                foreach ($locations as $location) {
                    $failedLocations[] = [
                        'location_id' => $location->id,
                        'error' => 'API request failed',
                        'details' => $responseBody
                    ];
                }
            }
        }

        // Prepare the final response
        $result = [
            'success' => $successLocations,
            'failed' => $failedLocations
        ];

        // Log the results
        Log::info('Customer Creation Result', $result);

        // Determine overall response status
        if (empty($failedLocations)) {
            return response()->json($result, 201); // Success code (created)
        } else {
            return response()->json($result, 500);
        }

    } catch (\Exception $e) {
        // Handle any unexpected errors
        Log::error('Unexpected error in customer creation', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => 'System error occurred',
            'message' => $e->getMessage()
        ], 500);
    }
}

//Re-submit sale to etims
    public function reSubmitSaleToEtims($invoiceNumber)
    {
        // Query the transaction based on the invoice number
        $transaction = Transaction::where('invoice_no', $invoiceNumber)->first();

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // Check if custom_field_1 is not null (indicating it's already recorded in ETIMS)
        if ($transaction->custom_field_1 !== null) {
            return response()->json(['error' => 'Transaction is already recorded in ETIMS'], 400);
        }

        // Pass the transaction data to the submitsaletoetims function
        $result = $this->submitSaleToEtims($transaction);

        if ($result && $result['status'] == 200) { // Check the status code
            Log::info("Sale re-submitted to ETIMs successfully for invoice number: $invoiceNumber");
            return response()->json(['message' => 'Sale submitted to ETIMs successfully'], 200); // Return the message and status from submitSaleToEtims
        } else {
            Log::error("Failed to re-submit sale to ETIMs for invoice number: $invoiceNumber. Error: " . $result['message']); // Log error with details
            return response()->json(['error' => $result['message']], 500); // Return the error and status from submitSaleToEtims
        }
    }


/**
 * Populate customers to etims
 */
public function populateCustomers()
{
    // Query contacts where type is 'customer' and custom_field1 is null or empty
    $customers = Contact::where('type', 'customer')
        ->where(function($query) {
            $query->whereNull('custom_field1')
                  ->orWhere('custom_field1', '');
        })
        ->get();

    // Track successful and failed customer imports
    $successfulImports = [];
    $failedImports = [];

    // Iterate through customers and attempt to create in ETIMS
    foreach ($customers as $customer) {
        try {
            // Specific name extraction logic
            $customerName = trim($customer->name);
            if (empty($customerName)) {
                $customerName = trim($customer->supplier_business_name);
            }

            // If both name and supplier_business_name are empty, use "Customer"
            if (empty($customerName)) {
                $customerName = "Customer";
            }

            // Prepare and process customer PIN
            $customerPin = trim($customer->tax_number ?? $customer->contact_id);
            $customerPin = substr($customerPin, 0, 11); // Trim to 11 characters from left
           //$customerPin = preg_replace('/[^A-Za-z0-9]/', '', $customerPin); // Remove non-alphanumeric characters

            // Prepare other customer data
            $customerEmail = trim($customer->email ?? $customer->contact_id . '@gmail.com');
            $customerPhone = trim($customer->mobile ?? $customer->contact_id);

            // Validation checks
            $validationErrors = [];

            if (empty($customerName)) {
                $validationErrors[] = 'No valid customer name found';
            }

            if (empty($customerPin)) {
                $validationErrors[] = 'No valid tax number/PIN found';
            }

            // Skip customer if critical fields are missing
            if (!empty($validationErrors)) {
                $failedImports[] = [
                    'id' => $customer->id,
                    'name' => $customerName ?: 'Unknown',
                    'errors' => $validationErrors,
                    'raw_data' => [
                        'name' => $customer->name,
                        'supplier_business_name' => $customer->supplier_business_name,
                        'tax_number' => $customer->tax_number
                    ]
                ];
                continue;
            }

            // Prepare customer data for request
            $customerData = [
                'customer_name' => $customerName,
                'customer_pin' => $customerPin,
                'customer_email' => $customerEmail, 
                'customer_phone_number' => $customerPhone
            ];

            // Create a request object with the customer data
            $request = new Request($customerData);

            // Call the existing createCustomer method
            $response = $this->createCustomer($request);

            // Parse the response
            $responseData = json_decode($response->getContent(), true);

            // Check if the import was successful
            $isSuccessful = isset($responseData['success']) && !empty($responseData['success']);

            if ($isSuccessful) {
                // Update custom_field1 to mark as processed
                $customer->custom_field1 = 'etims';
                $customer->save();

                $successfulImports[] = [
                    'id' => $customer->id,
                    'name' => $customerName,
                    'original_pin' => $customer->tax_number,
                    'truncated_pin' => $customerPin,
                    'etims_id' => $responseData['success'][0]['response']['id'] ?? null
                ];
            } else {
                // Failed import
                $failedImports[] = [
                    'id' => $customer->id,
                    'name' => $customerName,
                    'error' => 'Failed to create customer in ETIMS',
                    'original_pin' => $customer->tax_number,
                    'truncated_pin' => $customerPin,
                    'response' => $responseData,
                    'raw_data' => $customerData
                ];
            }
        } catch (\Exception $e) {
            $failedImports[] = [
                'id' => $customer->id,
                'name' => $customerName ?: 'Unknown',
                'error' => $e->getMessage(),
                'original_pin' => $customer->tax_number,
                'raw_data' => [
                    'name' => $customer->name,
                    'supplier_business_name' => $customer->supplier_business_name,
                    'tax_number' => $customer->tax_number
                ]
            ];
        }
    }

    // Log the results
    Log::info('Bulk Customer Import Results', [
        'total_customers' => count($customers),
        'successful_imports' => count($successfulImports),
        'failed_imports' => count($failedImports)
    ]);

    // Return a summary of the import
    return [
        'total_customers' => count($customers),
        'successful_imports' => count($successfulImports),
        'failed_imports' => count($failedImports),
        'successful_details' => $successfulImports,
        'failed_details' => $failedImports
    ];
}

 

public function createBusinessItem(array $data)
{
    try {
        // Group locations by API key to minimize redundant API calls
        $apiKeyLocationsMap = [];
        foreach ($data['product_locations'] as $locationId) {
            $apiKey = $this->retrieveApiKeyForLocation($locationId);
            
            if ($apiKey === null) {
                Log::error('No API key found for location', [
                    'location_id' => $locationId
                ]);

                return response()->json([
                    'error' => 'No API key found for location: ' . $locationId,
                    'status' => 'API_KEY_MISSING'
                ], 400);
            }

            // Group locations by their API key
            $apiKeyLocationsMap[$apiKey][] = $locationId;
        }

        // Track results for processed locations
        $locationResults = [];

        // Iterate through unique API keys
        foreach ($apiKeyLocationsMap as $apiKey => $locationsWithThisKey) {
            try {
                $client = new Client();
                
                Log::info('Sending Business Item Creation Request', [
                    'request_data' => $data,
                    'locations_with_key' => $locationsWithThisKey,
                    'api_key' => substr($apiKey, 0, 5) . '...' // Partially mask API key for security
                ]);

                $response = $client->post('https://api.digitax.tech/items', [
                    'json' => [
                        'active' => 'True',
                        'item_class_code' => $data['item_class_code'],
                        'item_type_code' => $data['item_type_code'],
                        'item_name' => $data['item_name'],
                        'origin_nation_code' => $data['origin_nation_code'],
                        'package_unit_code' => $data['package_unit_code'],
                        'quantity_unit_code' => $data['quantity_unit_code'],
                        'tax_type_code' => $data['tax_type_code'],
                        'default_unit_price' => (float) max(1, $this->cleanUnitPrice($data['default_unit_price'])),
                        'is_stock_item' => (bool)'True',
                        'callback_url' => $data['callback_url']
                    ],
                    'headers' => [
                        'X-API-Key' => $apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ]
                ]);
                
                $responseData = json_decode($response->getBody(), true);
                
                Log::info('Business Item Created Successfully', [
                    'response' => $responseData,
                    'item_name' => $data['item_name'],
                    'locations_with_key' => $locationsWithThisKey
                ]);

                // Extract and validate ETIMS data
                $etimsId = $responseData['id'] ?? null;
                $etimsItemCode = $responseData['etims_item_code'] ?? null;
                
                if (!$etimsId || !$etimsItemCode) {
                    throw new Exception('Missing required ETIMS data for locations: ' . implode(', ', $locationsWithThisKey));
                }

                // Update product_locations pivot table for ALL locations with this API key
                foreach ($locationsWithThisKey as $locationId) {
                    $updateResult = DB::table('product_locations')
                        ->where('product_id', $data['product_id'])
                        ->where('location_id', $locationId)
                        ->update([
                            'digitax_id' => $etimsId,
                            'item_code' => $etimsItemCode
                        ]);
                
                    Log::info('Product location updated successfully', [
                        'location_id' => $locationId,
                        'etims_id' => $etimsId,
                        'etims_item_code' => $etimsItemCode,
                        'rows_updated' => $updateResult
                    ]);

                    // Store results for this location
                    $locationResults[$locationId] = [
                        'etimsId' => $etimsId,
                        'etimsItemCode' => $etimsItemCode,
                        'rowsUpdated' => $updateResult
                    ];
                }
            
            } catch (RequestException $e) {
                // Log the error for these locations
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
                $responseBody = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : null;
                
                Log::error('Business Item API Error for Locations', [
                    'locations' => $locationsWithThisKey,
                    'status_code' => $statusCode,
                    'error_message' => $e->getMessage(),
                    'response_body' => $responseBody
                ]);

                // Add error details to location results for each location
                foreach ($locationsWithThisKey as $locationId) {
                    $locationResults[$locationId] = [
                        'error' => $e->getMessage(),
                        'status_code' => $statusCode
                    ];
                }
            } catch (Exception $e) {
                Log::error('Failed to update product locations', [
                    'locations' => $locationsWithThisKey,
                    'error' => $e->getMessage()
                ]);

                // Add error details to location results for each location
                foreach ($locationsWithThisKey as $locationId) {
                    $locationResults[$locationId] = [
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        // Determine overall status based on results
        $overallStatus = 'SUCCESS';
        $failedLocations = [];
        foreach ($locationResults as $locationId => $result) {
            if (isset($result['error'])) {
                $overallStatus = 'PARTIAL_ERROR';
                $failedLocations[] = $locationId;
            }
        }

        // Log comprehensive summary of all location results
        Log::info('Business Item Creation Summary', [
            'total_locations' => count($data['product_locations']),
            'status' => $overallStatus,
            'location_results' => $locationResults,
            'failed_locations' => $failedLocations
        ]);

        return response()->json([
            'location_results' => $locationResults,
            'status' => $overallStatus,
            'failed_locations' => $failedLocations
        ], 200);
        
    } catch (\Exception $e) {
        Log::error('Business Item Unexpected Global Error', [
            'error_message' => $e->getMessage(),
            'request_data' => $data,
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'An unexpected error occurred while processing your request',
            'status' => 'INTERNAL_ERROR'
        ], 500);
    }
}

   
 

    //Push default values to etims
    public function pushDefaultQuantitiesToEtims()
{
        try {
            // Total number of products in the system
            $totalProductsInSystem = Product::count();
            
            // Find products without ETIMS ID (null or empty)
            $productsWithoutEtimsId = Product::join('product_locations', 'products.id', '=', 'product_locations.product_id')
                                            ->where(function($query) {
                                                $query->whereNull('product_locations.digitax_id')
                                                    ->orWhere('product_locations.digitax_id', '');
                                            })
                                            ->select('products.*', 'product_locations.location_id')
                                            
                                            ->get();
            
            // Extract product details without ETIMS ID
            $productsWithoutEtimsIdDetails = $productsWithoutEtimsId->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name
                ];
            })->toArray();
            
            // Fetch products with null or empty stock_update in product_locations
            $products = Product::join('product_locations', 'products.id', '=', 'product_locations.product_id')
                ->where(function($query) {
                    $query->whereNull('product_locations.stock_update')
                        ->orWhere('product_locations.stock_update', '');
                })
                ->select('products.*', 'product_locations.location_id')
                // ->distinct()
                ->get();
            
            $totalProductsWithEtimsId = $products->count();
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($products as $product) {
                $productId = $product->id;
                $locationId = $product->location_id; // Use the location_id from product_locations
                $defaultQuantity = 10000000;
                
                // Attempt to adjust stock for the product
                $result = $this->addStockAdjustment($productId, $locationId, $defaultQuantity);
                
                if ($result['success']) {
                    // Update stock_update in product_locations
                    try {
                        DB::table('product_locations')
                            ->where('product_id', $productId)
                            ->where('location_id', $locationId)
                            ->update([
                                'stock_update' => 'etims'
                            ]);
                        
                        $successCount++;
                    } catch (\Exception $updateException) {
                        // Log error if updating product_locations fails
                        Log::error("Failed to update product_locations for product {$productId}", [
                            'product_id' => $productId,
                            'location_id' => $locationId,
                            'error' => $updateException->getMessage()
                        ]);
                        $failureCount++;
                    }
                } else {
                    // Log failure with product ID
                    Log::error("Stock adjustment failed for product {$productId}", [
                        'product_id' => $productId,
                        'location_id' => $locationId,
                        'response' => $result['response']
                    ]);
                    $failureCount++;
                }
            }
            
            // Log detailed information
            Log::channel('papertrail')->info("Stock adjustment process completed.", [
                'total_products_in_system' => $totalProductsInSystem,
                'products_processed' => $totalProductsWithEtimsId,
                'successful_adjustments' => $successCount,
                'failed_adjustments' => $failureCount,
                'products_without_etims_id_details' => $productsWithoutEtimsIdDetails
            ]);
            
            return [
                'total_products_in_system' => $totalProductsInSystem,
                'products_processed' => $totalProductsWithEtimsId,
                'successful_adjustments' => $successCount,
                'failed_adjustments' => $failureCount,
                'products_without_etims_id_details' => $productsWithoutEtimsIdDetails
            ];
        } catch (\Exception $e) {
            // Log any critical errors
            Log::channel('papertrail')->error("Critical error in stock adjustment process: " . $e->getMessage());
            
            return [
                'total_products_in_system' => 0,
                'products_processed' => 0,
                'successful_adjustments' => 0,
                'failed_adjustments' => 0,
                'products_without_etims_id_details' => [],
                'error' => $e->getMessage()
            ];
        }   
    }

    //Remove non-numeric characters except decimal point
    function cleanUnitPrice($price) {
        // Remove any non-numeric characters except decimal point
        $cleanPrice = preg_replace('/[^0-9.]/', '', $price);
        
        // Convert to float and round to 2 decimal places
        $formattedPrice = number_format(floatval($cleanPrice), 2, '.', '');
        
        return $formattedPrice;
    }

    public function updateBusinessItem($product, $request)
    {
        try {
            // Get product locations with their business API keys and ETIMS IDs (digitax_id)
            $product_locations = $product->product_locations()
                ->join('business_locations as bl', 'product_locations.location_id', '=', 'bl.id') // Join business_locations to get the API key
                ->select(
                    'bl.custom_field1 as api_key',   // Assuming 'custom_field1' is the API key in business_locations
                    'product_locations.digitax_id as etims_id', // Get digitax_id from product_locations
                    'product_locations.product_id as pivot_product_id',
                    'product_locations.location_id as pivot_location_id'
                )
                ->get();

            // Log the product locations for debugging
            Log::info('Retrieved product locations:', ['product_locations' => $product_locations]);

            // Prepare client for API calls
            $client = new Client();

            // Clean the price
            $cleanPrice = $this->cleanUnitPrice($request->input('single_dsp_inc_tax'));

            // Prepare results array
            $locationResults = [];

            // Process updates for each location
            foreach ($product_locations as $location) {
                try {
                    // Check if etims_id is null
                    if (empty($location->etims_id)) {
                        // Check for existing ETIMS ID with the same API key
                        $existing_location = $product_locations->firstWhere('api_key', $location->api_key);

                        if ($existing_location && !empty($existing_location->etims_id)) {
                            // Update the digitax_id in the product_locations table
                            $product->product_locations()
                                    ->where('product_locations.location_id', $location->pivot->location_id) // Fully qualify location_id
                                    ->update(['digitax_id' => $existing_location->etims_id]);

                            Log::info('Updated digitax_id for location', [
                                'location_id' => $location->pivot->location_id,
                                'etims_id' => $existing_location->etims_id
                            ]);

                            // Skip further processing
                            continue;
                        }

                        // Prepare data for creating a new business item
                        $createData = [
                            'item_name' => $product->name,
                            'default_unit_price' => $cleanPrice,
                            'product_locations' => [$location->pivot->location_id],
                            'origin_nation_code' => 'KE', // Example default
                            'package_unit_code' => 'BE', // Example default
                            'quantity_unit_code' => 'U', // Example default
                            'callback_url' => route('api.products.callback', ['product' => $product->id]), // Add appropriate callback URL
                        ];

                        // Call createBusinessItem to create the item for this location
                        $createResponse = $this->createBusinessItem($createData);

                        // Check if creation was successful
                        $responseData = json_decode($createResponse->getContent(), true);
                        if ($responseData['status'] !== 'SUCCESS') {
                            throw new \Exception("Failed to create business item for location: {$location->pivot_location_id}");
                        }

                        // Log the creation
                        Log::info('Business Item Created for Missing ETIMS ID', [
                            'location_id' => $location->pivot_location_id,
                            'product_id' => $product->id
                        ]);

                        // Continue to next location
                        continue;
                    }

                    // Perform the API update
                    $response = $client->put('https://api.digitax.tech/items/' . $location->etims_id, [
                        'json' => [
                            'active' => true,
                            'item_name' => $product->name,
                            'default_unit_price' => (float)$cleanPrice,
                        ],
                        'headers' => [
                            'X-API-Key' => $location->api_key,
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ]
                    ]);

                    // Parse response
                    $responseData = json_decode($response->getBody(), true);

                    // Log successful response
                    Log::info('Business Item Updated Successfully', [
                        'etims_id' => $location->etims_id,
                        'api_key' => $location->api_key,
                        'response' => $responseData
                    ]);

                    // Store success result
                    $locationResults[$location->pivot_location_id] = [
                        'success' => true,
                        'etims_id' => $location->etims_id,
                        'response' => $responseData
                    ];

                } catch (\Exception $e) {
                    // Log the error
                    Log::error('Business Item Update Error', [
                        'etims_id' => $location->etims_id,
                        'location_id' => $location->pivot_location_id,
                        'error_message' => $e->getMessage()
                    ]);

                    // Store failure result
                    $locationResults[$location->pivot_location_id] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log the overall results
            Log::info('All Business Item Updates Completed Successfully', [
                'total_locations' => count($product_locations),
                'successful_updates' => count($locationResults),
                'location_results' => $locationResults
            ]);

            return response()->json([
                'location_results' => $locationResults,
                'status' => 'SUCCESS',
            ], 200);

        } catch (\Exception $e) {
            // Log the overall error
            Log::info('Business Item Update Process Failed', [
                'error_message' => $e->getMessage(),
                'location_results' => $locationResults ?? []
            ]);

            // Return failure response
            return response()->json([
                'error' => 'An error occurred during the update process.',
                'status' => 'FAILURE',
                'location_results' => $locationResults ?? []
            ], 500);
        }
    }

    /**
     * Update product descriptions for all products.
     */
    

    public function updateProductDescriptions()
    {
        try {
            // Total number of products in the system
            $totalProductsInSystem = Product::count();

            // Fetch all products
            $products = Product::all();
            
            $totalProductsAffected = 0;
            $successfulUpdates = 0;
            $failedUpdates = 0;
            $failedProducts = [];

            foreach ($products as $product) {
                try {
                    // Update the product description
                    $product->product_description =  '<p>KE,BE,U</p>';
                    if ($product->save()) {
                        $successfulUpdates++;
                    } else {
                        $failedUpdates++;
                        $failedProducts[] = $product->name;
                    }
                    $totalProductsAffected++;
                } catch (\Exception $e) {
                    // Log failure with product name and error
                    Log::error("Failed to update product description for {$product->name}", [
                        'product_name' => $product->name,
                        'error' => $e->getMessage()
                    ]);
                    $failedUpdates++;
                    $failedProducts[] = $product->name;
                }
            }

            // Log detailed information
            Log::info("Product description update process completed.", [
                'total_products_in_system' => $totalProductsInSystem,
                'total_products_affected' => $totalProductsAffected,
                'successful_updates' => $successfulUpdates,
                'failed_updates' => $failedUpdates,
                'failed_products' => $failedProducts
            ]);

            return [
                'total_products_in_system' => $totalProductsInSystem,
                'total_products_affected' => $totalProductsAffected,
                'successful_updates' => $successfulUpdates,
                'failed_updates' => $failedUpdates,
                'failed_products' => $failedProducts
            ];

        } catch (\Exception $e) {
            // Log critical errors
            Log::error("Critical error in product description update process: " . $e->getMessage());

            return [
                'total_products_in_system' => 0,
                'total_products_affected' => 0,
                'successful_updates' => 0,
                'failed_updates' => 0,
                'failed_products' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Populate business items in etims
     */
    public function populateBusinessItems()
    {
        // Query products via product_locations with null digitax_id
        $products = Product::join('product_locations', 'products.id', '=', 'product_locations.product_id')
            ->leftJoin('variations', 'products.id', '=', 'variations.product_id')
            ->select(
                'products.id as product_id',
                'products.name as item_name',
                'variations.sell_price_inc_tax as default_unit_price',
                DB::raw('GROUP_CONCAT(DISTINCT product_locations.location_id) as location_ids')
            )
            ->whereNull('product_locations.digitax_id')
            ->whereNotNull('variations.sell_price_inc_tax')
            ->where('products.is_inactive', 0)
            ->groupBy('products.id', 'products.name', 'variations.sell_price_inc_tax')
            ->get();

        $successCount = 0;
        $failureCount = 0;
        $detailedResults = [];

        foreach ($products as $product) {
            try {
                // Prepare data for createBusinessItem method
                $locationIds = explode(',', $product->location_ids);

                $data = [
                    'product_id' => $product->product_id,
                    'item_name' => $product->item_name,
                    'default_unit_price' => $this->cleanUnitPrice($product->default_unit_price), 
                    'product_locations' => $locationIds,
                    'callback_url' => route('api.products.callback', ['product' => $product->product_id]),
                    'item_class_code' => "31160000",
                    'origin_nation_code' => 'KE',
                    'package_unit_code' => 'BE',
                    'quantity_unit_code' => 'U',
                    'item_type_code' => '2',
                    'tax_type_code' => 'B',
                    'is_stock_item' => true
                ];

                // Call createBusinessItem method
                $response = $this->createBusinessItem($data);
                $responseData = json_decode($response->getContent(), true);

                // Track detailed results
                $detailedResults[] = [
                    'product_id' => $product->product_id,
                    'item_name' => $product->item_name,
                    'status' => $responseData['status'],
                    'location_results' => $responseData['location_results']
                ];

                // Update success/failure counts based on overall status
                if ($responseData['status'] === 'SUCCESS') {
                    $successCount++;
                } else {
                    $failureCount++;
                }

            } catch (\Exception $e) {
                Log::channel('papertrail')->error('Failed to create Business Item', [
                    'product_id' => $product->product_id,
                    'error' => $e->getMessage()
                ]);

                $detailedResults[] = [
                    'product_id' => $product->product_id,
                    'item_name' => $product->item_name,
                    'status' => 'FAILED',
                    'error' => $e->getMessage()
                ];

                $failureCount++;
            }
        }

        // Log comprehensive summary
        Log::channel('papertrail')->info('Business Items Bulk Creation Summary', [
            'total_products' => $products->count(),
            'successful_creations' => $successCount,
            'failed_creations' => $failureCount,
            'detailed_results' => $detailedResults
        ]);

        return [
            'total_products' => $products->count(),
            'successful_creations' => $successCount,
            'failed_creations' => $failureCount,
            'detailed_results' => $detailedResults
        ];
    }


    /**
     * Delete business item from eTIMS
     *
     * @param Product $product Product model
     * @return \Illuminate\Http\JsonResponse Response from eTIMS API
     */
    public function deleteBusinessItem($product)
    {
        try {
            // Get product locations with their business API keys and ETIMS IDs (digitax_id)
            $product_locations = $product->product_locations()
                ->join('business_locations as bl', 'product_locations.location_id', '=', 'bl.id')
                ->select(
                    'bl.custom_field1 as api_key',
                    'product_locations.digitax_id as etims_id',
                    'product_locations.product_id as pivot_product_id',
                    'product_locations.location_id as pivot_location_id'
                )
                ->get();

            // Group locations by API keys
            $locations_by_api_key = $product_locations
                ->groupBy('api_key')
                ->filter(function ($locations, $api_key) {
                    return !empty($api_key);  // Ensure no empty API key groups
                });

            // Prepare client for API calls
            $client = new Client();

            // Prepare results array
            $locationResults = [];

            // Process deletions for each API key
            foreach ($locations_by_api_key as $api_key => $locations) {
                foreach ($locations as $location) {
                    try {
                        // Skip if no ETIMS ID (digitax_id)
                        if (empty($location->etims_id)) {
                            throw new \Exception("ETIMS ID (digitax_id) is missing for location ID: {$location->pivot_location_id}");
                        }

                        // Log the API request
                        Log::info('Sending Business Item Delete Request', [
                            'etims_id' => $location->etims_id,
                            'api_key' => $api_key . '...', // Masked for security
                        ]);

                        // Perform the API call using ETIMS ID (digitax_id) from product_locations
                        $response = $client->delete('https://api.digitax.tech/items/' . $location->etims_id, [
                            'headers' => [
                                'X-API-Key' => $api_key,
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/json',
                            ]
                        ]);

                        // Parse response
                        $responseData = json_decode($response->getBody(), true);

                        // Log successful response
                        Log::info('Business Item Deleted Successfully', [
                            'etims_id' => $location->etims_id,
                            'api_key' => $api_key,
                            'response' => $responseData
                        ]);

                        // Store success result
                        $locationResults[$location->pivot_location_id] = [
                            'success' => true,
                            'etims_id' => $location->etims_id,
                            'response' => $responseData
                        ];

                    } catch (\Exception $e) {
                        // Log the error
                        Log::error('Business Item Delete Error', [
                            'etims_id' => $location->etims_id,
                            'location_id' => $location->pivot_location_id,
                            'error_message' => $e->getMessage()
                        ]);

                        // Store failure result
                        $locationResults[$location->pivot_location_id] = [
                            'success' => false,
                            'error' => $e->getMessage()
                        ];

                        // Break out of the loop to mark overall failure
                        throw $e;
                    }
                }
            }

            // If we reach here, all deletions were successful
            Log::info('All Business Item Deletions Completed Successfully', [
                'total_locations' => count($product_locations),
                'successful_deletions' => count($locationResults),
                'location_results' => $locationResults
            ]);

            return response()->json([
                'location_results' => $locationResults,
                'status' => 'SUCCESS',
            ], 200);

        } catch (\Exception $e) {
            // Log the overall error
            Log::info('Business Item Delete Process Failed', [
                'error_message' => $e->getMessage(),
                'location_results' => $locationResults ?? []
            ]);

            // Mark all deletions as failed
            return response()->json([
                'error' => 'An error occurred during the delete process. All deletions rolled back.',
                'status' => 'FAILURE',
                'location_results' => $locationResults ?? []
            ], 500);
        }
    }

//Purchase items
// public function sendPurchaseItems($purchaseId, $purchaseLines)
// {
//     try {
//         $client = new Client();

//         // Log the API request
//         Log::channel('papertrail')->info('Sending Purchase Items Request', [
//             'purchase_id' => $purchaseId,
//             'endpoint' => "https://api.digitax.tech/purchases/{$purchaseId}/purchase_items/link_item"
//         ]);
       
//         $items = [];
//         foreach ($purchaseLines as $line) {
//             $itemId = $line->product->product_custom_field1;
             
//             if ($itemId) {
//                 $items[] = [
//                     'item_sequence_number' => $line->id,
//                     'item_id' => $itemId
//                 ];
//             }
//         }
        
//         if (empty($items)) {
//             Log::channel('papertrail')->info("No ETIMS items to send for purchase $purchaseId");
//             return false;
//         }

//         $response = $client->request('POST', "https://api.digitax.tech/purchases/{$purchaseId}/purchase_items/link_item", [
//             'body' => json_encode(['items' => $items]),
//             'headers' => [
//                 'X-API-Key' => self::API_KEY,
//                 'Accept' => 'application/json',
//                 'Content-Type' => 'application/json',
//             ]
//         ]);

//         // Log successful response
//         Log::channel('papertrail')->info('Purchase Items Send Response', [
//             'purchase_id' => $purchaseId,
//             'status' => $response->getStatusCode(),
//             'response' => json_decode($response->getBody(), true)
//         ]);

//         return json_decode($response->getBody(), true);
//     } catch (\Exception $e) {
//         // Log error with detailed information
//         Log::channel('papertrail')->error('Purchase Items Send Failed', [
//             'purchase_id' => $purchaseId,
//             'error' => $e->getMessage(),
//             'request_details' => [
//                 'url' => "https://api.digitax.tech/purchases/{$purchaseId}/purchase_items/link_item",
//                 'items' => array_map(function($item) {
//                     return [
//                         'item_sequence_number' => $item['item_sequence_number'],
//                         'item_id' => $item['item_id']
//                     ];
//                 }, $items)
//             ]
//         ]);
    
//         throw $e;
//     }   
// }

//Adjust stock input values for multiple locations
public function addStockAdjustmentMultiple($input) {
    // Normalize input to always be an array of items
    $stockItems = is_array($input) ? $input : [$input];
    
    $results = [];
    
    foreach ($stockItems as $item) {
        try {
            // Extract parameters, allowing flexible input
            $product_id = is_array($item) ? $item['product_id'] : $item->product_id;
            $location_id = is_array($item) ? $item['location_id'] : $item->location_id;
            $remaining_quantity = is_array($item) ? $item['remaining_quantity'] : $item->remaining_quantity;
            
            $product = Product::findOrFail($product_id);
            
            // Get the API key for the location
            $apiKey = $this->retrieveApiKeyForLocation($location_id);
            
            // Retrieve the digitax_id for the product and location
            $digitax_id = $this->getDigitaxId($product_id, $location_id);
            
            if (empty($digitax_id)) {
                Log::info("ETIMS Update Skipped: No custom item ID for product {$product_id}");
                
                $results[] = [
                    'product_id' => $product_id,
                    'location_id' => $location_id,
                    'success' => false,
                    'message' => 'No custom item ID found'
                ];
                
                continue;
            }
            
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', 'https://api.digitax.tech/stock/adjust', [
                'body' => json_encode([
                    'item_id' => (string)$digitax_id,
                    'quantity' => $remaining_quantity,
                    'action' => 'add',
                    'type' => 'adjustment'
                ]),
                'headers' => [
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            // Parse response content
            $responseBody = json_decode($response->getBody()->getContents(), true);
            
            // Ensure the response contains the ID
            if (isset($responseBody['id'])) {
                $etimsId = $responseBody['id'];
                
                // Log successful update with ETIMS ID
                Log::info("ETIMS Stock Update Successful", [
                    'product_id' => $product_id,
                    'etims_id' => $etimsId,
                    'location_id' => $location_id,
                    'quantity' => $remaining_quantity,
                    'response' => $responseBody,
                ]);
                
                $results[] = [
                    'product_id' => $product_id,
                    'location_id' => $location_id,
                    'success' => true,
                    'etims_id' => $etimsId,
                    'response' => $responseBody
                ];
            } else {
                Log::error("ETIMS Update Failed: No ID in response", [
                    'product_id' => $product_id,
                    'etims_id' => $product->product_custom_field1,
                    'location_id' => $location_id,
                    'remaining_quantity' => $remaining_quantity,
                    'response' => $responseBody
                ]);
                
                $results[] = [
                    'product_id' => $product_id,
                    'location_id' => $location_id,
                    'success' => false,
                    'response' => $responseBody
                ];
            }
        } catch (\Exception $e) {
            // Log error details
            Log::error("ETIMS Update Failed: " . $e->getMessage(), [
                'product_id' => $product_id ?? 'Unknown',
                'location_id' => $location_id ?? 'Unknown',
                'remaining_quantity' => $remaining_quantity ?? 'Unknown'
            ]);
            
            $results[] = [
                'product_id' => $product_id ?? null,
                'location_id' => $location_id ?? null,
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Check if all updates were successful
    $allSuccessful = count(array_filter($results, function($result) { 
        return $result['success'] === true; 
    })) === count($results);
    
    return [
        'success' => $allSuccessful,
        'results' => $results
    ];
}

//Adjust stock to put new values , the action is add, single location
public function addStockAdjustment($product_id, $location_id, $remaining_quantity) {
    try {
        $product = Product::findOrFail($product_id);

         //Get the API key for the location
         $apiKey = $this->retrieveApiKeyForLocation($location_id);

         // Retrieve the digitax_id for the product and location
         $digitax_id = $this->getDigitaxId($product_id, $location_id);
        
        if (empty($digitax_id)) {
            Log::info("ETIMS Update Skipped: No custom item ID for product {$product_id}");
            return ['success' => false, 'response' => null];
        }
        
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', 'https://api.digitax.tech/stock/adjust', [
            'body' => json_encode([
                'item_id' => (string)$digitax_id,
                'quantity' => $remaining_quantity,
                'action' => 'add',
                'type' => 'adjustment'
            ]),
            'headers' => [
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ]);
        
        // Parse response content
        $responseBody = json_decode($response->getBody()->getContents(), true);
        
        // Ensure the response contains the ID
        if (isset($responseBody['id'])) {
            $etimsId = $responseBody['id'];
            
            // Log successful update with ETIMS ID
            Log::info("ETIMS Stock Update Successful", [
                'product_id' => $product_id,
                'etims_id' => $etimsId,
                'location_id' => $location_id,
                'quantity' => $remaining_quantity,
                'response' => $responseBody,
            ]);
            
            return [
                'success' => true, 
                'response' => $responseBody
            ];
        } else {
            Log::error("ETIMS Update Failed: No ID in response", [
                'product_id' => $product_id,
                'etims_id' => $product->product_custom_field1,
                'location_id' => $location_id,
                'remaining_quantity' => $remaining_quantity,
                'response' => $responseBody
            ]);
            
            return [
                'success' => false, 
                'response' => $responseBody
            ];
        }
    } catch (\Exception $e) {
        // Log error details
        Log::error("ETIMS Update Failed: " . $e->getMessage(), [
            'product_id' => $product_id,
            'etims_id' => $product->product_custom_field1,
            'location_id' => $location_id,
            'remaining_quantity' => $remaining_quantity
        ]);
        
        return [
            'success' => false, 
            'response' => null
        ];
    }
 }

//Adjust stock values in the etims subtract items
    public function newStockAdjustmentSubtract($product_id, $location_id, $quantity) {
        try {
            $product = Product::findOrFail($product_id);

            // Retrieve the digitax_id for the product and location
            $digitax_id = $this->getDigitaxId($product_id, $location_id);

            if (empty($digitax_id)) {
                Log::info("ETIMS Update Skipped: No custom item ID for product {$product_id}");
                return ['success' => false, 'response' => null];
            }

             //Get the API key for the location
            $apiKey = $this->retrieveApiKeyForLocation($location_id);

            // Ensure quantity is a numeric value
            $quantity = floatval($quantity);

            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', 'https://api.digitax.tech/stock/adjust', [
                'body' => json_encode([
                    'item_id' => (string)$digitax_id,
                    'quantity' => $quantity,
                    'action' => 'deduct',
                    'type' => 'adjustment'
                ]),
                'headers' => [
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);

            // Parse response content
            $responseBody = json_decode($response->getBody()->getContents(), true);

            // Ensure the response contains the ID
            if (isset($responseBody['id'])) {
                $etimsId = $responseBody['id'];

                // Log successful update with ETIMS ID
                Log::info("ETIMS Stock Subtract Successful", [
                    'product_id' => $product_id,
                    'etims_id' => $etimsId,
                    'location_id' => $location_id,
                    'quantity' => $quantity,
                    'response' => $responseBody,
                ]);

                return [
                    'success' => true,
                    'response' => $responseBody
                ];
            } else {
                Log::error("ETIMS Subtract Update Failed: No ID in response", [
                    'product_id' => $product_id,
                    'etims_id' => $product->product_custom_field1,
                    'location_id' => $location_id,
                    'quantity' => $quantity,
                    'response' => $responseBody
                ]);

                return [
                    'success' => false,
                    'response' => $responseBody
                ];
            }
        } catch (\Exception $e) {
            // Log error details
            Log::error("ETIMS Subtract Update Failed: " . $e->getMessage(), [
                'product_id' => $product_id,
                'etims_id' => $product->product_custom_field1,
                'location_id' => $location_id,
                'quantity' => $quantity
            ]);

            return [
                'success' => false,
                'response' => null
            ];
        }
    }

    // Function to retrieve the digitax_id
    public function getDigitaxId($product_id, $location_id)
    {
        // Query the product_locations table based on product_id and location_id
        $productLocation = \DB::table('product_locations')
            ->where('product_id', $product_id)
            ->where('location_id', $location_id)
            ->first();

        // Log a failure if digitax_id is not found
        if (!$productLocation) {
            Log::error('Failed to find digitax_id for Product ID: ' . $product_id . ' at Location ID: ' . $location_id);
            return null; // Return null if not found
        }

        return $productLocation->digitax_id;
    }

    // Sale to etims 
     /**
 * Submit sale to eTIMS system
 * 
 * @param mixed $transaction
 * @return array Contains 'url' and 'status' 
 */
public function submitSaleToEtimsRetry($transaction)
{
    try {
        // Prepare items data
        $items = [];
        foreach ($transaction->sell_lines as $sell_line) {
            $product = Product::find($sell_line->product_id);
            Log::info('Product found with ID: ' . $sell_line->product_id);

            // Retrieve the digitax_id for the product and location
            $digitax_id = $this->getDigitaxId($sell_line->product_id, $transaction->location_id);

            $items[] = [
                'id' => (string)$digitax_id, // Use custom_field1 as eTIMS item ID
                'quantity' => $this->num_uf($sell_line->quantity),
                'unit_price' => $this->num_uf($sell_line->unit_price_inc_tax),
                'discount_rate' => 0,
                'discount_amount' => 0,
            ];
        }

        // Fetch customer details
        $customer = Contact::find($transaction->contact_id);
        $paymentTypeCode = $this->getPaymentTypeCode($transaction);
        $businessId = $this->retrieveBusinessIdForLocation($transaction->location_id);
        $offlineUrl = 'https://etims.ke/r/' . $businessId;
        $apiKey = $this->retrieveApiKeyForLocation($transaction->location_id);
        $date =  Carbon::now()->format('Y-m-d');
      

        $payload = [
            // 'sale_date' =>$date,
            'items' => $items,
            'customer_pin' => $customer->tax_number  ?? $customer->contact_id, // Use custom_field1 as PIN
            'customer_name' => $customer->name ?? $customer->supplier_business_name,
            'trader_invoice_number' => (string) $transaction->id,
            'invoice_number' => $transaction->invoice_no,
            'receipt_type_code' => 'S', // Default to sale receipt
            'payment_type_code' => $paymentTypeCode,
            'invoice_status_code' => '02', // Active invoice
            'callback_url' => 'https://crown.techsavyprofessionals.co.ke/api/etims/callback', // Define this route
            'general_invoice_details' => 'Sale transaction #' . $transaction->invoice_no,
        ];

        // Log the payload being sent
        Log::info('Sale being sent to Digitax API', [
            'transaction_id' => $transaction->id,
            'payload' => $payload
        ]);

        // Initialize Guzzle Client
        $client = new Client();

        // Send POST request
        $response = $client->post('https://api.digitax.tech/sales', [
            'json' => $payload, // Automatically encode as JSON
            'headers' => [
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
        
        // $response = $client->post('https://api.digitax.tech/sales', [
        //     'json' => $payload,
        //     'headers' => [
        //         'Host' => 'api.digitax.tech', // Ensure hostname matches the certificate
        //         'X-API-Key' => $apiKey,
        //         'Accept' => 'application/json',
        //         'Content-Type' => 'application/json',
        //     ],
        //     'curl' => [
        //         CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2, // Force TLS 1.2
        //         CURLOPT_SSL_VERIFYHOST => 2, // Verify the hostname in the certificate
        //     ],
        // ]);

        // Log the response
        Log::info('eTIMS Sale Submission Response', [
            'transaction_id' => $transaction->id,
            'original_payment_type' => $paymentTypeCode,
            'response' => json_decode($response->getBody(), true),
        ]);

        // Extract the link and return it for the receipt printer
        if ($response) {
            $responseData = json_decode($response->getBody(), true);
            $etimsUrl = $offlineUrl . '/' . $responseData['trader_invoice_number'];
            Log::info('Constructed eTIMS URL: ' . $etimsUrl);

            // Save the URL in custom_field_1 of the transaction
            $transaction->custom_field_1 = $etimsUrl;
            $transaction->custom_field_2 = $responseData['digitax_id'];
            $transaction->custom_field_3 = $responseData['invoice_number'];
            $transaction->custom_field_4 = $responseData['serial_number'];
            $transaction->custom_field_5 = $responseData['receipt_number'];
            $transaction->custom_field_6 = $responseData['date'];
            $transaction->custom_field_7 = $responseData['time'];
            $transaction->custom_field_8 = $responseData['customer_pin'];
            $transaction->save();

            Log::info('eTIMS URL saved in transaction record', [
                'transaction_id' => $transaction->id,
                'etims_url' => $etimsUrl
            ]);

            // Return an array with URL and status code
            return [
                'url' => $etimsUrl,
                'status' => 200 // Successful submission
            ];
        }

        // Return failure status if no response
        return [
            'url' => false,
            'status' => 500 // Internal server error
        ];

    } catch (\Exception $e) {
        Log::error('eTIMS Sale Submission Error', [
            'message' => $e->getMessage(),
            'transaction_id' => $transaction->id
        ]);

        // Return failure status with exception details
        return [
            'url' => false,
            'status' => $e->getCode() ?: 500 // Use exception code or default to 500
        ];
    }
}

public function submitSaleToEtims($transaction)
{
    $businessId = $this->retrieveBusinessIdForLocation($transaction->location_id);
    
    try {
        // Prepare items data
        $items = [];
        
        $allExists = true;
        
        foreach ($transaction->sell_lines as $sell_line) {
            $product = Product::find($sell_line->product_id);
            Log::info('Product found with ID: ' . $sell_line->product_id);

            // Retrieve the digitax_id for the product and location
            $digitax_id = $this->getDigitaxId($sell_line->product_id, $transaction->location_id);

//            $items[] = [
//                'id' => (string)$digitax_id, // Use custom_field1 as eTIMS item ID
//                'quantity' => $this->num_uf($sell_line->quantity),
//                'unit_price' => $this->num_uf($sell_line->unit_price_inc_tax),
//                'discount_rate' => 0,
//                'discount_amount' => 0,
//            ];
            $quant_sold = $this->num_uf($sell_line->quantity);
            $unit_price_sold = $this->num_uf($sell_line->unit_price_inc_tax);
            
            if($allExists)
                $allExists = !empty($digitax_id);
            
            $items[] = [
                'id' => (string)$digitax_id, // Use custom_field1 as eTIMS item ID
                'quantity' => $quant_sold,
                'unit_price' => $unit_price_sold,
                'total_amount' => round(($quant_sold * $unit_price_sold), 2),
                'discount_rate' => 0,
                'discount_amount' => 0,
            ];
        }

        // Fetch customer details
        $customer = Contact::find($transaction->contact_id);
        $paymentTypeCode = $this->getPaymentTypeCode($transaction);
        
        
        $liveUrl = 'https://api.digitax.tech/ke/v2/sales';
        $apiKey = $this->retrieveApiKeyForLocation($transaction->location_id);
        $date =  Carbon::now()->format('Y-m-d');
      

//        $payload = [
//            // 'sale_date' =>$date,
//            'items' => $items,
//            'customer_pin' => $customer->tax_number  ?? $customer->contact_id, // Use custom_field1 as PIN
//            'customer_name' => $customer->name ?? $customer->supplier_business_name,
//            'trader_invoice_number' => (string) $transaction->id,
//            'invoice_number' => $transaction->invoice_no,
//            'receipt_type_code' => 'S', // Default to sale receipt
//            'payment_type_code' => $paymentTypeCode,
//            'invoice_status_code' => '02', // Active invoice
//            'callback_url' => 'https://crown.techsavyprofessionals.co.ke/api/etims/callback', // Define this route
//            'general_invoice_details' => 'Sale transaction #' . $transaction->invoice_no,
//        ];
        $payload = [
            'sale_date' =>$date,
            'items' => $items,
            'customer_tin' => $customer->tax_number  ?? $customer->contact_id, // Use custom_field1 as PIN
            'customer_name' => $customer->name ?? $customer->supplier_business_name,
            'trader_invoice_number' => (string) $transaction->id,
            'invoice_number' => $transaction->invoice_no,
            'receipt_type_code' => 'S', // Default to sale receipt
            'payment_type_code' => $paymentTypeCode,
            'invoice_status_code' => '02', // Active invoice
            'callback_url' => 'https://crown.techsavyprofessionals.co.ke/api/etims/callback', // Define this route
            'invoice_details' => 'Sale transaction #' . $transaction->invoice_no,
        ];

        // Log the payload being sent
        Log::info('Sale being sent to Digitax API', [
            'transaction_id' => $transaction->id,
            'payload' => $payload
        ]);
        
        
        //Don't even send just return url
        if($allExists === false) {
            $offlineUrl = 'https://etims.ke/r/' . $businessId."/".$transaction->id;
            $transaction->custom_field_1 = $offlineUrl;
            $transaction->save();
            Log::error('eTIMS Sale Submission Error(Item ID error) Resubmit Later', [
                'message' => "Error ",
                'transaction_id' => $transaction->id
            ]);
            
            return [
                'url' => $offlineUrl,
                'status' => 200 // Successful submission
            ];
            
        }

        // Initialize Guzzle Client
        $client = new Client();

        // Send POST request
//        $response = $client->post('https://api.digitax.tech/sales', [
//            'json' => $payload, // Automatically encode as JSON
//            'headers' => [
//                'X-API-Key' => $apiKey,
//                'Accept' => 'application/json',
//                'Content-Type' => 'application/json',
//            ],
//        ]);
        $response = $client->post($liveUrl, [
            'json' => $payload, // Automatically encode as JSON
            'headers' => [
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
        
        // $response = $client->post('https://api.digitax.tech/sales', [
        //     'json' => $payload,
        //     'headers' => [
        //         'Host' => 'api.digitax.tech', // Ensure hostname matches the certificate
        //         'X-API-Key' => $apiKey,
        //         'Accept' => 'application/json',
        //         'Content-Type' => 'application/json',
        //     ],
        //     'curl' => [
        //         CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2, // Force TLS 1.2
        //         CURLOPT_SSL_VERIFYHOST => 2, // Verify the hostname in the certificate
        //     ],
        // ]);

        // Log the response
        Log::info('eTIMS Sale Submission Response 4', [
            'transaction_id' => $transaction->id,
            'original_payment_type' => $paymentTypeCode,
            'response' => json_decode($response->getBody(), true),
        ]);

        // Extract the link and return it for the receipt printer
        if ($response) {
            $responseData = json_decode($response->getBody(), true);
//            $etimsUrl = $offlineUrl . '/' . $responseData['trader_invoice_number'];
            Log::info('Constructed eTIMS URL: ' . $responseData['offline_url']);

            // Save the URL in custom_field_1 of the transaction
//            $transaction->custom_field_1 = $etimsUrl;
//            $transaction->custom_field_2 = $responseData['digitax_id'];
//            $transaction->custom_field_3 = $responseData['invoice_number'];
//            $transaction->custom_field_4 = $responseData['serial_number'];
//            $transaction->custom_field_5 = $responseData['receipt_number'];
//            $transaction->custom_field_6 = $responseData['date'];
//            $transaction->custom_field_7 = $responseData['time'];
//            $transaction->custom_field_8 = $responseData['customer_pin'];
            $transaction->custom_field_1 = $responseData['offline_url'];
            $transaction->custom_field_2 = $responseData['id'];
            $transaction->custom_field_3 = $responseData['invoice_number'];
            $transaction->custom_field_4 = $responseData['serial_number'];
            $transaction->custom_field_5 = $responseData['receipt_number'];
            $transaction->custom_field_6 = $responseData['date'];
            $transaction->custom_field_7 = $responseData['time'];
            $transaction->custom_field_8 = $customer->tax_number  ?? $customer->contact_id;
            $transaction->save();

            Log::info('eTIMS URL saved in transaction record', [
                'transaction_id' => $transaction->id,
                'etims_url' => $responseData['offline_url']//$etimsUrl
            ]);

            // Return an array with URL and status code
            return [
                'url' => $responseData['offline_url'],//$etimsUrl,
                'status' => 200 // Successful submission
            ];
        }

        // Return failure status if no response
//        return [
//            'url' => false,
//            'status' => 500 // Internal server error
//        ];

        //This block executes even when digita have an error
        $offlineUrl = 'https://etims.ke/r/' . $businessId."/".$transaction->id;
        $transaction->custom_field_1 = $offlineUrl;
        $transaction->save();
        Log::error('500 Digitax eTIMS Sale Submission Error Now (Item ID error) Resubmit Later', [
            'message' => "Error ",
            'transaction_id' => $transaction->id
        ]);

        return [
            'url' => $offlineUrl,
            'status' => 200 // Successful submission
        ];

    } catch (\Exception $e) {
        Log::error('eTIMS Sale Submission Error Resubmit Later', [
            'message' => $e->getMessage(),
            'transaction_id' => $transaction->id
        ]);
        $offlineUrl = 'https://etims.ke/r/' . $businessId."/".$transaction->id;

        // Return failure status with exception details
        // return [
        //     'url' => false,
        //     'status' => $e->getCode() ?: 500 // Use exception code or default to 500
        // ];
        // return $this->submitSaleToEtimsRetry($transaction);
        return [
                'url' => $offlineUrl,
                'status' => 200 // Successful submission
            ];
    }
}

    public function submitSaleToEtimsOffline($transaction)
    {
        $businessId = $this->retrieveBusinessIdForLocation($transaction->location_id);

        try {
            // Prepare items data
            $items = [];

            $allExists = true;

            foreach ($transaction->sell_lines as $sell_line) {
                $product = Product::find($sell_line->product_id);
                Log::info('Product found with ID: ' . $sell_line->product_id);

                // Retrieve the digitax_id for the product and location
                $digitax_id = $this->getDigitaxId($sell_line->product_id, $transaction->location_id);

//            $items[] = [
//                'id' => (string)$digitax_id, // Use custom_field1 as eTIMS item ID
//                'quantity' => $this->num_uf($sell_line->quantity),
//                'unit_price' => $this->num_uf($sell_line->unit_price_inc_tax),
//                'discount_rate' => 0,
//                'discount_amount' => 0,
//            ];
                $quant_sold = $this->num_uf($sell_line->quantity);
                $unit_price_sold = $this->num_uf($sell_line->unit_price_inc_tax);

                if($allExists)
                    $allExists = !empty($digitax_id);

                $items[] = [
                    'id' => (string)$digitax_id, // Use custom_field1 as eTIMS item ID
                    'quantity' => $quant_sold,
                    'unit_price' => $unit_price_sold,
                    'total_amount' => round(($quant_sold * $unit_price_sold), 2),
                    'discount_rate' => 0,
                    'discount_amount' => 0,
                ];
            }

            // Fetch customer details
            $customer = Contact::find($transaction->contact_id);
            $paymentTypeCode = $this->getPaymentTypeCode($transaction);


            $liveUrl = 'https://api.digitax.tech/ke/v2/sales';
            $apiKey = $this->retrieveApiKeyForLocation($transaction->location_id);
            $date =  Carbon::parse($transaction->transaction_date)->format('Y-m-d');


//        $payload = [
//            // 'sale_date' =>$date,
//            'items' => $items,
//            'customer_pin' => $customer->tax_number  ?? $customer->contact_id, // Use custom_field1 as PIN
//            'customer_name' => $customer->name ?? $customer->supplier_business_name,
//            'trader_invoice_number' => (string) $transaction->id,
//            'invoice_number' => $transaction->invoice_no,
//            'receipt_type_code' => 'S', // Default to sale receipt
//            'payment_type_code' => $paymentTypeCode,
//            'invoice_status_code' => '02', // Active invoice
//            'callback_url' => 'https://crown.techsavyprofessionals.co.ke/api/etims/callback', // Define this route
//            'general_invoice_details' => 'Sale transaction #' . $transaction->invoice_no,
//        ];
            $payload = [
                'sale_date' =>$date,
                'items' => $items,
                'customer_tin' => $customer->tax_number  ?? $customer->contact_id, // Use custom_field1 as PIN
                'customer_name' => $customer->name ?? $customer->supplier_business_name,
                'trader_invoice_number' => (string) $transaction->transaction_uid,
                'invoice_number' => $transaction->invoice_no,
                'receipt_type_code' => 'S', // Default to sale receipt
                'payment_type_code' => $paymentTypeCode,
                'invoice_status_code' => '02', // Active invoice
                'callback_url' => 'https://crown.techsavyprofessionals.co.ke/api/etims/callback', // Define this route
                'invoice_details' => 'Sale transaction #' . $transaction->invoice_no,
            ];

            // Log the payload being sent
            Log::info('Sale being sent to Digitax API', [
                'transaction_id' => $transaction->transaction_uid,
                'payload' => $payload
            ]);



            // Initialize Guzzle Client
            $client = new Client();

            // Send POST request
//        $response = $client->post('https://api.digitax.tech/sales', [
//            'json' => $payload, // Automatically encode as JSON
//            'headers' => [
//                'X-API-Key' => $apiKey,
//                'Accept' => 'application/json',
//                'Content-Type' => 'application/json',
//            ],
//        ]);
            $response = $client->post($liveUrl, [
                'json' => $payload, // Automatically encode as JSON
                'headers' => [
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            // $response = $client->post('https://api.digitax.tech/sales', [
            //     'json' => $payload,
            //     'headers' => [
            //         'Host' => 'api.digitax.tech', // Ensure hostname matches the certificate
            //         'X-API-Key' => $apiKey,
            //         'Accept' => 'application/json',
            //         'Content-Type' => 'application/json',
            //     ],
            //     'curl' => [
            //         CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2, // Force TLS 1.2
            //         CURLOPT_SSL_VERIFYHOST => 2, // Verify the hostname in the certificate
            //     ],
            // ]);

            // Log the response
            Log::info('eTIMS Sale Submission Response', [
                'transaction_id' => $transaction->id,
                'original_payment_type' => $paymentTypeCode,
                'response' => json_decode($response->getBody(), true),
            ]);

            // Extract the link and return it for the receipt printer
            if ($response) {
                $responseData = json_decode($response->getBody(), true);
//            $etimsUrl = $offlineUrl . '/' . $responseData['trader_invoice_number'];
                Log::info('Constructed eTIMS URL: ' . $responseData['offline_url']);

                // Save the URL in custom_field_1 of the transaction
//            $transaction->custom_field_1 = $etimsUrl;
//            $transaction->custom_field_2 = $responseData['digitax_id'];
//            $transaction->custom_field_3 = $responseData['invoice_number'];
//            $transaction->custom_field_4 = $responseData['serial_number'];
//            $transaction->custom_field_5 = $responseData['receipt_number'];
//            $transaction->custom_field_6 = $responseData['date'];
//            $transaction->custom_field_7 = $responseData['time'];
//            $transaction->custom_field_8 = $responseData['customer_pin'];
//                $transaction->custom_field_1 = $responseData['offline_url'];
                $transaction->custom_field_2 = $responseData['id'];
                $transaction->custom_field_3 = $responseData['invoice_number'];
                $transaction->custom_field_4 = $responseData['serial_number'];
                $transaction->custom_field_5 = $responseData['receipt_number'];
                $transaction->custom_field_6 = $responseData['date'];
                $transaction->custom_field_7 = $responseData['time'];
                $transaction->custom_field_8 = $customer->tax_number  ?? $customer->contact_id;
//                $transaction->synced = true;
                $transaction->save();

                Log::info('eTIMS URL saved in transaction record', [
                    'transaction_id' => $transaction->id,
                    'etims_url' => $responseData['offline_url']//$etimsUrl
                ]);

                // Return an array with URL and status code
                return [
                    'url' => $responseData['offline_url'],//$etimsUrl,
                    'status' => 200 // Successful submission
                ];
            }

            // Return failure status if no response
            return [
                'url' => false,
                'status' => 500 // Internal server error
            ];

        } catch (\Exception $e) {
            Log::error('eTIMS Sale Submission Error Resubmit Later', [
                'message' => $e->getMessage(),
                'transaction_id' => $transaction->id
            ]);
            $offlineUrl = 'https://etims.ke/r/' . $businessId."/".$transaction->transaction_uid;

            // Return failure status with exception details
            // return [
            //     'url' => false,
            //     'status' => $e->getCode() ?: 500 // Use exception code or default to 500
            // ];
            // return $this->submitSaleToEtimsRetry($transaction);
            return [
                'url' => $offlineUrl,
                'status' => 200 // Successful submission
            ];
        }
    }
    
    
    
    //Get API key per location
    /**
     * Retrieve the API key for a specific business location
     * 
     * @param int $locationId The ID of the business location
     * @return string|null The API key from custom_field1 of the business location
     */
    public function retrieveApiKeyForLocation($locationId)
    {
        try {
            // Find the business location
            $businessLocation = BusinessLocation::findOrFail($locationId);
            
            // Retrieve the API key from custom_field1
            $apiKey = $businessLocation->custom_field1;
            
            // Validate the API key
            if (empty($apiKey)) {
                Log::error('API key is empty for location ID: ' . $locationId);
                return null;
            }
            
            return $apiKey;
        } catch (\Exception $e) {
            Log::error('Error retrieving API key for location', [
                'location_id' => $locationId,
                'error_message' => $e->getMessage()
            ]);
            
            return null;
        }
        return $apiKey;
    }

    /**
     * Retrieve the business ID for a specific business location
     * 
     * @param int $locationId The ID of the business location
     * @return string|null The business ID from custom_field2 of the business location
     */
    public function retrieveBusinessIdForLocation($locationId)
    {
        try {
            // Find the business location
            $businessLocation = BusinessLocation::findOrFail($locationId);

            // Retrieve the business ID from custom_field2
            $businessId = $businessLocation->custom_field2;

            // Validate the business ID
            if (empty($businessId)) {
                Log::error('Business ID is empty for location ID: ' . $locationId);
                return null;
            }

            return $businessId;
        } catch (\Exception $e) {
            Log::error('Error retrieving business ID for location', [
                'location_id' => $locationId,
                'error_message' => $e->getMessage()
            ]);

            return null;
        }
    }



    // Helper method to get payment type code
    private function getPaymentTypeCode($transaction)
    {
        // If payment status is due, it's a credit transaction
        if ($transaction->payment_status === 'due') {
            return '02'; // CREDIT
        }

        // Fetch the transaction by ID
        $transaction = Transaction::with('payment_lines')->find($transaction->id);

        if (!$transaction) {
            Log::error("Transaction not found with ID: {transaction->id}");
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // Retrieve the related transaction payments
        $transactionPayments = $transaction->payment_lines;

        if ($transactionPayments->isEmpty()) {
            Log::warning("No transaction payments found for transaction ID: {$transaction->id}");
            return response()->json(['message' => 'No payments found for this transaction']);
        }

        // Check transaction payments
        $transactionPayments = $transaction->payment_lines;
    

        // Initialize payment methods
        $paymentMethods = $transactionPayments->pluck('method')->unique()->toArray();

        // Precise mapping based on eTIMS codes
        $paymentTypeMappings = [
            'cash' => '01', // CASH
            'credit' => '02', // CREDIT
            'card' => '05', // DEBIT & CREDIT CARD
            'cheque' => '04', // BANK CHECK
            'mobile_money' => '06', // MOBILE MONEY
            'other' => '07' // OTHER MEANS OF PAYMENT
        ];

        // If multiple payment methods
        if (count($paymentMethods) > 1) {
            return '07'; // CASH/CREDIT
        }

        // Single payment method
        $method = strtolower(reset($paymentMethods));
        
        // Return mapped code or default to other
        return $paymentTypeMappings[$method] ?? '07';
    }

     // Utility method to safely convert to numeric
     private function num_uf($amount)
     {
         return floatval(str_replace(',', '', $amount));
     }
     
     private function getPaymentTypeCodeCredit($transaction)
    {
    // If payment status is due, it's a credit transaction
    if ($transaction->payment_status === 'due') {
        return '02'; // CREDIT
    }

    // Fetch the transaction by ID
    $transaction = Transaction::with('payment_lines')->find($transaction->id);

    if (!$transaction) {
        Log::error("Transaction not found with ID: {$transaction->id}");
        return response()->json(['error' => 'Transaction not found'], 404);
    }

    // Retrieve the related transaction payments
    $transactionPayments = $transaction->payment_lines;

    if ($transactionPayments->isEmpty()) {
        Log::warning("No transaction payments found for transaction ID: {$transaction->id}");
        return response()->json(['message' => 'No payments found for this transaction']);
    }

    // Initialize payment methods
    $paymentMethods = $transactionPayments->pluck('method')->unique()->toArray();

    // Precise mapping based on eTIMS codes
    $paymentTypeMappings = [
        'cash' => '01', // CASH
        'credit' => '02', // CREDIT
        'card' => '05', // DEBIT & CREDIT CARD
        'cheque' => '04', // BANK CHECK
        'mobile_money' => '06', // MOBILE MONEY
        'other' => '07' // OTHER MEANS OF PAYMENT
    ];

    // If multiple payment methods
    if (count($paymentMethods) > 1) {
        return '07'; // CASH/CREDIT
    }

    // Single payment method
    $method = reset($paymentMethods);

    if ($method !== false) {
        // Match the method in a case-insensitive way
        foreach ($paymentTypeMappings as $key => $code) {
            if (strcasecmp($method, $key) === 0) {
                return $code;
            }
        }
    } else {
        Log::warning("Empty or invalid payment methods for transaction ID: {$transaction->id}");
    }

    // Default to OTHER
    return '07';
    }

    //Submit credit note
   //Submit credit note
    public function submitCreditNoteToEtims($transaction)
{
    try {
        // Prepare items data
        $items = [];
        Log::info('Transaction ID', ['Transaction ID' => $transaction->id]);
        
        // Fetch original sale transaction
        $originalSale = Transaction::find($transaction->return_parent_id);
        
        if (!$originalSale) {
            Log::error('Original Sale not found', ['return_parent_id' => $transaction->return_parent_id]);
            return false;
        }
        
        Log::info('Original Sale Transaction', ['original_transaction_id' => $originalSale->id]);
        
        // Debug: Log original sale sell lines
        Log::info('Original Sale Sell Lines', [
            'count' => $originalSale->sell_lines->count(),
            'lines' => $originalSale->sell_lines->toArray()
        ]);

        // Fetch original sell lines
        $originalSellLines = $originalSale->sell_lines;
        
        if ($originalSellLines->isEmpty()) {
            Log::error('Original Sale has no items', ['original_transaction_id' => $originalSale->id]);
            return false;
        }
        
        foreach ($originalSellLines as $sell_line) {
            $product = Product::find($sell_line->product_id);
            if (!$product) {
                Log::warning('Product not found', ['product_id' => $sell_line->product_id]);
                continue;
            }

            // Retrieve the digitax_id for the product and location
            $digitax_id = $this->getDigitaxId($sell_line->product_id, $transaction->location_id);
        
            $items[] = [
                'id' => (string)$digitax_id, // Use custom_field1 as eTIMS item ID
                'quantity' => $this->num_uf($sell_line->quantity), // Original sale quantity
                'unit_price' => $this->num_uf($sell_line->unit_price),
                'discount_rate' => $originalSale->discount_amount > 0 
                    ? ($originalSale->discount_amount / $originalSale->total_before_tax) * 100 
                    : 0,
                'discount_amount' => $this->num_uf($sell_line->line_discount_amount)
            ];
        
            Log::info('Item Added', [
                'product_id' => $sell_line->product_id,
                'quantity' => $sell_line->quantity,
                'unit_price' => $sell_line->unit_price
            ]);
        }
        
        if (empty($items)) {
            Log::error('No items prepared for eTIMS submission', ['original_transaction_id' => $originalSale->id]);
            return false;
        }
        
        Log::info('Items Prepared for eTIMS Submission', ['items' => $items]);

        // Fetch customer details
        $customer = Contact::find($originalSale->contact_id);
        Log::info('Customer Retrieved', ['customer' => $customer]);
        $businessId = $this->retrieveBusinessIdForLocation($transaction->location_id);
        $offlineUrl = 'https://etims.ke/r/' . $businessId;

        // Determine payment type code with explicit handling
        $paymentTypeCode = $this->getPaymentTypeCodeCredit($originalSale);

        // Ensure invoice number is numeric
        $originalInvoiceNumber = is_numeric($originalSale->invoice_no) 
                                ? (int)$originalSale->invoice_no 
                                : 0;

        $callbackUrl = url('/api/etims/callback');
        // If localhost, ensure it's http not https
        $callbackUrl = str_replace('https://localhost', 'http://localhost', $callbackUrl);
        // ===== END FIX =====

        // Prepare the payload for the POST request
        $payload = [
            'items' => $items,
            'customer_pin' => $customer->tax_number ?? $customer->contact_id , // Use custom_field1 as PIN
            'customer_name' => $customer->name ?? $customer->supplier->business_name,
            'trader_invoice_number' => (string)$transaction->invoice_no,
            'invoice_number' => $transaction->id,
            'receipt_type_code' => 'R', // Credit Note Receipt Type
            'payment_type_code' => $paymentTypeCode,
            'invoice_status_code' => '01', // Active invoice
            'callback_url' => 'https://crown.techsavyprofessionals.co.ke/api/etims/callback', // Define this route
            'general_invoice_details' => 'Credit Note for transaction #' . $transaction->invoice_no,
            'original_invoice_number' => (int)$originalSale->custom_field_3 // Reference to original sale invoice
        ];

        //Get the API key for the location
        $apiKey = $this->retrieveApiKeyForLocation($transaction->location_id);

        // Log the payload being sent
        Log::info('Credit Note being sent to Digitax API', [
            'transaction_id' => $transaction->id,
            'payload' => $payload
        ]);

        // Initialize Guzzle Client
        $client = new Client();

        // Send POST request
        $response = $client->post('https://api.digitax.tech/sales', [
            'json' => $payload, // Automatically encode as JSON
            'headers' => [
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        // Check for successful response
        if ($response->getStatusCode() === 201) {
            // Decode the JSON response body
            $responseData = json_decode($response->getBody(), true);

            // Log the response data
            Log::info('eTIMS Credit Note Submission Response', [
                'transaction_id' => $transaction->id,
                'original_transaction_id' => $originalSale->id,
                'customer_id' => $originalSale->contact->tax_number,
                'response' => $responseData
            ]);

            // Extract the URL for the receipt
            $etimsUrl = $offlineUrl . '/' . $responseData['trader_invoice_number'];

            if ($etimsUrl) {
                Log::info('Constructed eTIMS URL for Credit Note: ' . $etimsUrl);

                // Save the URL in custom_field_4 of the transaction
                $transaction->custom_field_1 = $etimsUrl;
                $transaction->custom_field_2 = $responseData['digitax_id'];
                $transaction->custom_field_3 = $responseData['invoice_number'];
                $transaction->custom_field_4 = $responseData['serial_number'];
                $transaction->custom_field_5 = $responseData['receipt_number'];
                $transaction->custom_field_6 = $responseData['date'];
                $transaction->custom_field_7 = $responseData['time'];
                $transaction->save();
                
                Log::info('eTIMS URL saved in credit note transaction record', [
                    'transaction_id' => $transaction->id,
                    'etims_url' => $etimsUrl
                ]);

                return $etimsUrl;
            }
        } else {
            $responseBody = (string) $response->getBody();  // Get the full response body as string

            Log::error('eTIMS Credit Note Submission Failed', [
                'transaction_id' => $transaction->id,
                'status_code' => $response->getStatusCode(),
                'response_body' => $responseBody
            ]);
        }

        return false;

    } catch (RequestException $e) {
        Log::error('Guzzle Request Exception', [
            'message' => $e->getMessage(),
            'transaction_id' => $transaction->id
        ]);
    } catch (\Exception $e) {
        Log::error('eTIMS Credit Note Submission Error', [
            'message' => $e->getMessage(),
            'transaction_id' => $transaction->id,
            'trace' => $e->getTraceAsString()
        ]);
    }

    return false;
}
    // Separate method for determining payment type code
    private function determinePaymentTypeCode($transaction)
    {
        // Log the input transaction details
        Log::info('Determining Payment Type Code', [
            'transaction_id' => $transaction->id,
            'payment_status' => $transaction->payment_status,
            'payment_lines_count' => $transaction->payment_lines ? $transaction->payment_lines->count() : 0
        ]);

        // If payment status is due, it's a credit transaction
        if ($transaction->payment_status === 'due') {
            return '02'; // CREDIT
        }

        // Check if payment lines exist and have methods
        $paymentLines = $transaction->payment_lines ?? collect();

        // If no payment lines, default to other payment method
        if ($paymentLines->isEmpty()) {
            Log::warning("No transaction payments found, defaulting to other payment method", [
                'transaction_id' => $transaction->id
            ]);
            return '07'; // OTHER MEANS OF PAYMENT
        }

        // Payment type mappings
        $paymentTypeMappings = [
            'cash' => '01', // CASH
            'credit' => '02', // CREDIT
            'card' => '05', // DEBIT & CREDIT CARD
            'cheque' => '04', // BANK CHECK
            'mobile_money' => '06', // MOBILE MONEY
            'other' => '07' // OTHER MEANS OF PAYMENT
        ];

        // Get unique payment methods
        $paymentMethods = $paymentLines->pluck('method')
            ->filter()
            ->unique()
            ->map('strtolower')
            ->toArray();

        // Log the found payment methods
        Log::info('Payment Methods Found', [
            'methods' => $paymentMethods
        ]);

        // If multiple payment methods
        if (count($paymentMethods) > 1) {
            return '07'; // CASH/CREDIT
        }

        // Single payment method
        $method = reset($paymentMethods);
        
        // Determine and log the payment code
        $paymentCode = $paymentTypeMappings[$method] ?? '07';

        Log::info('Payment Method Determined', [
            'transaction_id' => $transaction->id,
            'payment_method' => $method,
            'payment_code' => $paymentCode
        ]);

        return $paymentCode;
    }
    
    /**
     * Populate business items in ETIMS and set default quantities
     */
    public function populateEtimsItemsAndQuantities()
    {
        try {
            // Get products that need ETIMS processing
            $products = Product::join('product_locations', 'products.id', '=', 'product_locations.product_id')
                ->leftJoin('variations', 'products.id', '=', 'variations.product_id')
                ->select(
                    'products.id as product_id',
                    'products.name as item_name',
                    'variations.sell_price_inc_tax as default_unit_price',
                    DB::raw('GROUP_CONCAT(DISTINCT product_locations.location_id) as location_ids')
                )
                ->whereNull('product_locations.digitax_id')
                ->orWhere('product_locations.digitax_id', '=', '') // Check for empty string
                ->whereNotNull('variations.sell_price_inc_tax')
                ->where('products.is_inactive', 0)
                ->groupBy('products.id', 'products.name', 'variations.sell_price_inc_tax')
                ->get();

            $results = [
                'total_products' => $products->count(),
                'etims_creation' => [
                    'success' => 0,
                    'failure' => 0
                ],
                'quantity_setting' => [
                    'success' => 0,
                    'failure' => 0
                ],
                'detailed_results' => []
            ];

            foreach ($products as $product) {
                $productResult = [
                    'product_id' => $product->product_id,
                    'item_name' => $product->item_name,
                    'etims_status' => null,
                    'quantity_status' => null
                ];

                try {
                    // Step 1: Create Business Item in ETIMS
                    $locationIds = explode(',', $product->location_ids);
                    $data = [
                        'product_id' => $product->product_id,
                        'item_name' => $product->item_name,
                        'default_unit_price' => $this->cleanUnitPrice($product->default_unit_price),
                        'product_locations' => $locationIds,
                        'callback_url' => route('api.products.callback', ['product' => $product->product_id]),
                        'item_class_code' => "50200000",
                        'origin_nation_code' => 'KE',
                        'package_unit_code' => 'BE',
                        'quantity_unit_code' => 'U',
                        'item_type_code' => '2',
                        'tax_type_code' => 'B',
                        'is_stock_item' => true
                    ];

                    $response = $this->createBusinessItem($data);
                    $responseData = json_decode($response->getContent(), true);

                    $productResult['etims_status'] = $responseData['status'];
                    
                    if ($responseData['status'] === 'SUCCESS') {
                        $results['etims_creation']['success']++;

                        // Step 2: Set Default Quantities (5000)
                        foreach ($locationIds as $locationId) {
                            $quantityResult = $this->addStockAdjustment(
                                $product->product_id,
                                $locationId,
                                10000000// Default quantity as requested
                            );

                            if ($quantityResult['success']) {
                                // Update stock_update status
                                DB::table('product_locations')
                                    ->where('product_id', $product->product_id)
                                    ->where('location_id', $locationId)
                                    ->update([
                                        'stock_update' => 'etims'
                                    ]);
                                
                                $results['quantity_setting']['success']++;
                                $productResult['quantity_status'] = 'SUCCESS';
                            } else {
                                $results['quantity_setting']['failure']++;
                                $productResult['quantity_status'] = 'FAILED';
                                $productResult['quantity_error'] = $quantityResult['response'];
                            }
                        }
                    } else {
                        $results['etims_creation']['failure']++;
                        $productResult['etims_error'] = $responseData['message'] ?? 'Unknown error';
                    }
                } catch (\Exception $e) {
                    $results['etims_creation']['failure']++;
                    $productResult['etims_status'] = 'FAILED';
                    $productResult['error'] = $e->getMessage();
                    
                    Log::error('Failed to process product in ETIMS', [
                        'product_id' => $product->product_id,
                        'error' => $e->getMessage()
                    ]);
                }

                $results['detailed_results'][] = $productResult;
            }

            // Log final results for debugging
            Log::info('ETIMS Population and Quantity Setting Complete', [
                'Summary' => $this->formatSummaryForLogging($results),
                'Detailed Results' => $this->formatResultsForLogging($results['detailed_results']),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Critical error in ETIMS population process', [
                'error' => $e->getMessage()
            ]);

            return [
                'total_products' => 0,
                'etims_creation' => ['success' => 0, 'failure' => 0],
                'quantity_setting' => ['success' => 0, 'failure' => 0],
                'detailed_results' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
    //Helper function to format summary for logging
    protected function formatSummaryForLogging(array $results): string
    {
        return sprintf(
            "Total Products: %d\nETIMS Creation - Success: %d, Failure: %d\nQuantity Setting - Success: %d, Failure: %d",
            $results['total_products'],
            $results['etims_creation']['success'],
            $results['etims_creation']['failure'],
            $results['quantity_setting']['success'],
            $results['quantity_setting']['failure']
        );
    }

    // Helper function to format detailed results
    protected function formatResultsForLogging(array $detailedResults): string
    {
        if (empty($detailedResults)) {
            return "No detailed results available.";
        }

        return implode(PHP_EOL, array_map(function ($result) {
            return sprintf(
                "Product ID: %s, Name: %s, ETIMS Status: %s, Quantity Status: %s",
                $result['product_id'] ?? 'N/A',
                $result['item_name'] ?? 'N/A',
                $result['etims_status'] ?? 'N/A',
                $result['quantity_status'] ?? 'N/A'
            );
        }, $detailedResults));
    }
    
    //Check products without digitax id
    public function checkProductsWithoutDigitaxId()
    {
        try {
            // Fetch products without digitax_id
            $products = DB::table('products')
                ->join('product_locations', 'products.id', '=', 'product_locations.product_id')
                ->leftJoin('variations', 'products.id', '=', 'variations.product_id')
                ->select(
                    'products.id as product_id',
                    'products.name as item_name',
                    'variations.sell_price_inc_tax as default_unit_price',
                    DB::raw('GROUP_CONCAT(DISTINCT product_locations.location_id) as location_ids')
                )
                ->whereNull('product_locations.digitax_id')
                ->orWhere('product_locations.digitax_id', '=', '') // Empty string
                ->whereNotNull('variations.sell_price_inc_tax')
                ->where('products.is_inactive', 0)
                ->groupBy('products.id', 'products.name', 'variations.sell_price_inc_tax')
                ->get();

            // Format products for readable output
            $formattedProducts = $this->formatProductsForOutput($products);

            // Return response as plain text
            return response($formattedProducts, 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            // Handle and log errors
            Log::error('Error checking products without digitax_id', ['error' => $e->getMessage()]);

            return response("Error: " . $e->getMessage(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }
    
        /**
     * Helper function to format product details
     */
    protected function formatProductsForOutput($products)
    {
        if ($products->isEmpty()) {
            return "No products found without digitax_id.";
        }

        return $products->map(function ($product) {
            return sprintf(
                "Product ID: %s, Name: %s, Unit Price: %s, Location IDs: %s",
                $product->product_id,
                $product->item_name,
                $product->default_unit_price,
                $product->location_ids
            );
        })->implode(PHP_EOL); // Join each product detail with a new line
    }


}