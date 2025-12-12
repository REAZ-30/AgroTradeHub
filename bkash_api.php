<?php
/**
 * Dummy bKash API for AgroTradeHub
 * This simulates bKash payment verification for demonstration purposes only
 * In a real application, this would connect to the actual bKash API
 */

class BkashAPI {
    
    // Test/dummy transaction data for demonstration
    private $dummyTransactions;
    
    // Dummy merchant account
    private $merchantAccount;
    
    public function __construct() {
        // Initialize dummy transactions in constructor
        $this->dummyTransactions = [
            'TRX123456789' => [
                'amount' => 0.00, // 0 means accept any amount
                'status' => 'success',
                'mobile' => '01712345678',
                'timestamp' => '2025-12-08 10:30:00'
            ],
            'TRX987654321' => [
                'amount' => 0.00, // 0 means accept any amount
                'status' => 'success',
                'mobile' => '01812345678',
                'timestamp' => '2025-12-08 11:15:00'
            ],
            'TRX111222333' => [
                'amount' => 0.00, // 0 means accept any amount
                'status' => 'success',
                'mobile' => '01912345678',
                'timestamp' => '2025-12-08 12:45:00'
            ],
            'TRX444555666' => [
                'amount' => 0.00, // 0 means accept any amount
                'status' => 'failed',
                'mobile' => '01712345678',
                'timestamp' => '2025-12-08 09:20:00'
            ],
            'TRX777888999' => [
                'amount' => 0.00, // 0 means accept any amount
                'status' => 'success',
                'mobile' => '01312345678',
                'timestamp' => '2025-12-08 14:30:00'
            ],
            // For demo testing - auto success for specific patterns
            'DEMO123456' => [
                'amount' => 0.00, // Will match any amount
                'status' => 'success',
                'mobile' => '01700000000',
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'TEST123456' => [
                'amount' => 0.00, // Will match any amount
                'status' => 'success',
                'mobile' => '01800000000',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->merchantAccount = [
            'number' => '017XXXXXXXX',
            'name' => 'AgroTradeHub',
            'type' => 'Merchant'
        ];
    }
    
    /**
     * Verify a bKash payment transaction
     * 
     * @param string $transactionId The bKash transaction ID
     * @param float $amount The expected amount to verify
     * @param string $mobile (Optional) The mobile number used for payment
     * @return array Verification result with success status and details
     */
    public function verifyPayment($transactionId, $amount, $mobile = null) {
        // Simulate API processing delay
        usleep(300000); // 0.3 seconds delay (reduced from 0.8 for faster testing)
        
        // Clean input
        $transactionId = trim(strtoupper($transactionId));
        $amount = floatval($amount);
        
        // Log the verification attempt (for debugging)
        $this->logVerificationAttempt($transactionId, $amount, $mobile);
        
        // Validate transaction ID format
        if (!$this->isValidTransactionId($transactionId)) {
            return $this->createResponse(
                false,
                'Invalid transaction ID format. Valid format: TRX followed by numbers or just numbers (min 10 digits)',
                $transactionId,
                $amount,
                'invalid_format'
            );
        }
        
        // Validate amount
        if ($amount <= 0) {
            return $this->createResponse(
                false,
                'Invalid amount. Amount must be greater than 0',
                $transactionId,
                $amount,
                'invalid_amount'
            );
        }
        
        // Check if this is a known test transaction
        if (isset($this->dummyTransactions[$transactionId])) {
            $txn = $this->dummyTransactions[$transactionId];
            
            if ($txn['status'] === 'success') {
                // For demo transactions with amount 0, accept any amount
                // OR if amount matches exactly, accept it
                if ($txn['amount'] == 0 || abs($txn['amount'] - $amount) < 0.01) {
                    return $this->createResponse(
                        true,
                        'Payment verified successfully',
                        $transactionId,
                        $amount,
                        'verified',
                        [
                            'merchant' => $this->merchantAccount['name'],
                            'merchant_number' => $this->merchantAccount['number'],
                            'customer_mobile' => $mobile ?: $txn['mobile'],
                            'transaction_time' => $txn['timestamp'],
                            'note' => 'Demo transaction - Amount accepted for testing'
                        ]
                    );
                } else {
                    return $this->createResponse(
                        false,
                        'Amount mismatch. Expected: $' . number_format($amount, 2) . ', Found: $' . number_format($txn['amount'], 2),
                        $transactionId,
                        $amount,
                        'amount_mismatch'
                    );
                }
            } else {
                return $this->createResponse(
                    false,
                    'Transaction failed in bKash system',
                    $transactionId,
                    $amount,
                    'failed'
                );
            }
        }
        
        // For demo purposes, simulate verification logic
        
        // Pattern 1: TRX followed by numbers (6+ digits) - more lenient
        if (preg_match('/^TRX\d{6,}$/i', $transactionId)) {
            // Accept any TRX transaction for demo
            return $this->createResponse(
                true,
                'Payment verified successfully',
                $transactionId,
                $amount,
                'verified',
                [
                    'merchant' => $this->merchantAccount['name'],
                    'merchant_number' => $this->merchantAccount['number'],
                    'customer_mobile' => $mobile ?: $this->generateRandomMobile(),
                    'transaction_time' => date('Y-m-d H:i:s', time() - rand(60, 3600)),
                    'note' => 'Demo transaction - For development purposes only'
                ]
            );
        }
        
        // Pattern 2: Just numbers (6+ digits) - more lenient
        if (preg_match('/^\d{6,}$/', $transactionId)) {
            // Accept any numeric transaction for demo
            return $this->createResponse(
                true,
                'Payment verified successfully',
                $transactionId,
                $amount,
                'verified',
                [
                    'merchant' => $this->merchantAccount['name'],
                    'merchant_number' => $this->merchantAccount['number'],
                    'customer_mobile' => $mobile ?: $this->generateRandomMobile(),
                    'transaction_time' => date('Y-m-d H:i:s', time() - rand(60, 7200)),
                    'note' => 'Demo transaction - For development purposes only'
                ]
            );
        }
        
        // Pattern 3: Any transaction ID that's not empty - accept it for demo
        if (!empty($transactionId) && strlen($transactionId) >= 6) {
            return $this->createResponse(
                true,
                'Payment verified successfully',
                $transactionId,
                $amount,
                'verified',
                [
                    'merchant' => $this->merchantAccount['name'],
                    'merchant_number' => $this->merchantAccount['number'],
                    'customer_mobile' => $mobile ?: $this->generateRandomMobile(),
                    'transaction_time' => date('Y-m-d H:i:s'),
                    'note' => 'Demo transaction - Accepted for testing'
                ]
            );
        }
        
        // Only reject if transaction ID is obviously invalid
        return $this->createResponse(
            false,
            'Invalid transaction ID. Please enter a valid bKash transaction ID',
            $transactionId,
            $amount,
            'invalid'
        );
    }
    
    /**
     * Check if a mobile number is valid for bKash
     * 
     * @param string $mobile The mobile number to validate
     * @return array Validation result
     */
    public function validateMobile($mobile) {
        // Remove any non-digit characters
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        
        if (empty($mobile)) {
            return [
                'valid' => false,
                'message' => 'Mobile number cannot be empty',
                'formatted' => ''
            ];
        }
        
        // Check if it's a valid Bangladeshi mobile number
        if (preg_match('/^01[3-9]\d{8}$/', $mobile)) {
            return [
                'valid' => true,
                'message' => 'Valid bKash mobile number',
                'formatted' => $mobile,
                'operator' => $this->detectOperator($mobile)
            ];
        }
        
        // For demo, accept any 11-digit number starting with 01
        if (preg_match('/^01\d{9}$/', $mobile)) {
            return [
                'valid' => true,
                'message' => 'Valid mobile number (demo)',
                'formatted' => $mobile,
                'operator' => 'Unknown (demo)'
            ];
        }
        
        return [
            'valid' => false,
            'message' => 'Invalid Bangladeshi mobile number format. Should be 11 digits starting with 01',
            'formatted' => $mobile
        ];
    }
    
    /**
     * Get payment instructions for bKash
     * 
     * @param float $amount The amount to pay
     * @param string $reference Payment reference
     * @return array Payment instructions
     */
    public function getPaymentInstructions($amount, $reference = '') {
        $instructions = [
            'merchant' => [
                'name' => $this->merchantAccount['name'],
                'number' => $this->merchantAccount['number'],
                'type' => $this->merchantAccount['type']
            ],
            'amount' => $amount,
            'reference' => $reference ?: 'AgroTradeHub Order',
            'steps' => [
                '1. Dial *247# from your bKash registered mobile',
                '2. Choose "Send Money"',
                '3. Enter Merchant Number: ' . $this->merchantAccount['number'],
                '4. Enter Amount: $' . number_format($amount, 2),
                '5. Enter Reference: ' . ($reference ?: 'AgroTradeHub'),
                '6. Enter your bKash PIN to complete the payment'
            ],
            'important_notes' => [
                'Keep the transaction ID for verification',
                'Transaction ID will be sent via SMS from bKash',
                'Payment should reflect immediately',
                'Contact bKash customer care at 16247 for any issues'
            ],
            'test_info' => [
                'For demo purposes, you can use:',
                'Transaction ID: TRX123456789 or any TRX followed by numbers',
                'Or any numeric ID with 6+ digits',
                'Mobile: Any 11-digit number starting with 01',
                'Amount: Any positive amount will be accepted'
            ]
        ];
        
        return $instructions;
    }
    
    /**
     * Generate a test transaction ID for development
     * 
     * @return string Test transaction ID
     */
    public function generateTestTransactionId() {
        $prefixes = ['TRX', 'BKASH', 'BTRX', 'TXN'];
        $prefix = $prefixes[array_rand($prefixes)];
        $number = date('Ymd') . rand(1000, 9999);
        
        return $prefix . $number;
    }
    
    /**
     * Simulate initiating a payment (not actually initiating, just for demo)
     * 
     * @param float $amount Payment amount
     * @param string $mobile Customer mobile number
     * @return array Initiation response
     */
    public function initiatePayment($amount, $mobile) {
        // Validate mobile
        $mobileValidation = $this->validateMobile($mobile);
        if (!$mobileValidation['valid']) {
            return [
                'success' => false,
                'message' => 'Invalid mobile number: ' . $mobileValidation['message'],
                'payment_id' => null
            ];
        }
        
        // Generate a payment ID
        $paymentId = 'PAY' . date('YmdHis') . rand(100, 999);
        
        // Simulate initiation
        usleep(200000); // 0.2 seconds delay
        
        return [
            'success' => true,
            'message' => 'Payment initiated successfully',
            'payment_id' => $paymentId,
            'amount' => $amount,
            'mobile' => $mobileValidation['formatted'],
            'instructions' => 'Check your phone for bKash payment prompt',
            'note' => 'Demo response - No actual payment initiated'
        ];
    }
    
    /**
     * Check transaction status (simulated)
     * 
     * @param string $transactionId Transaction ID to check
     * @return array Status information
     */
    public function checkTransactionStatus($transactionId) {
        usleep(200000); // 0.2 seconds delay
        
        // Check if it's a known dummy transaction
        if (isset($this->dummyTransactions[$transactionId])) {
            $txn = $this->dummyTransactions[$transactionId];
            return [
                'transaction_id' => $transactionId,
                'status' => $txn['status'],
                'amount' => $txn['amount'],
                'timestamp' => $txn['timestamp'],
                'message' => ucfirst($txn['status']) . ' transaction'
            ];
        }
        
        // For demo, assume success for any valid-looking transaction ID
        if (strlen($transactionId) >= 6) {
            return [
                'transaction_id' => $transactionId,
                'status' => 'success',
                'amount' => rand(10, 500) . '.00',
                'timestamp' => date('Y-m-d H:i:s', time() - rand(60, 86400)),
                'message' => 'Success (demo)'
            ];
        }
        
        return [
            'transaction_id' => $transactionId,
            'status' => 'pending',
            'amount' => '0.00',
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Pending verification (demo)'
        ];
    }
    
    /**
     * Helper method to create standardized response
     */
    private function createResponse($success, $message, $transactionId, $amount, $status, $additionalData = []) {
        $response = [
            'success' => $success,
            'message' => $message,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => $status,
            'verified' => $success,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($additionalData)) {
            $response = array_merge($response, $additionalData);
        }
        
        return $response;
    }
    
    /**
     * Validate transaction ID format (more lenient for demo)
     */
    private function isValidTransactionId($transactionId) {
        // Accept: TRX followed by numbers, or just numbers, or any non-empty string for demo
        if (empty($transactionId)) {
            return false;
        }
        
        // Minimum length check
        if (strlen($transactionId) < 6) {
            return false;
        }
        
        // Accept almost anything for demo
        return true;
    }
    
    /**
     * Generate a random Bangladeshi mobile number
     */
    private function generateRandomMobile() {
        $prefixes = ['013', '014', '015', '016', '017', '018', '019'];
        $prefix = $prefixes[array_rand($prefixes)];
        $number = rand(1000000, 9999999);
        
        return $prefix . $number;
    }
    
    /**
     * Detect mobile operator
     */
    private function detectOperator($mobile) {
        $prefix = substr($mobile, 0, 3);
        
        $operators = [
            '013' => 'Grameenphone',
            '014' => 'Banglalink',
            '015' => 'TeleTalk',
            '016' => 'Airtel',
            '017' => 'Grameenphone',
            '018' => 'Robi',
            '019' => 'Banglalink'
        ];
        
        return $operators[$prefix] ?? 'Unknown';
    }
    
    /**
     * Log verification attempts (for debugging)
     */
    private function logVerificationAttempt($transactionId, $amount, $mobile) {
        // For debugging, you can uncomment this
        /*
        $logMessage = sprintf(
            "[%s] Verification attempt: TXN=%s, Amount=$%.2f, Mobile=%s\n",
            date('Y-m-d H:i:s'),
            $transactionId,
            $amount,
            $mobile ?: 'N/A'
        );
        error_log($logMessage, 3, __DIR__ . '/bkash_verification.log');
        */
    }
}

/**
 * Helper function to easily verify bKash payment
 * 
 * @param string $transactionId bKash transaction ID
 * @param float $amount Expected amount
 * @param string $mobile (Optional) Mobile number
 * @return array Verification result
 */
function verifyBkashPayment($transactionId, $amount, $mobile = null) {
    $bkash = new BkashAPI();
    return $bkash->verifyPayment($transactionId, $amount, $mobile);
}

/**
 * Helper function to get payment instructions
 * 
 * @param float $amount Payment amount
 * @param string $reference Payment reference
 * @return array Payment instructions
 */
function getBkashInstructions($amount, $reference = '') {
    $bkash = new BkashAPI();
    return $bkash->getPaymentInstructions($amount, $reference);
}
?>