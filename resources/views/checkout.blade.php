@extends('layouts.app')

@section('title', 'PayU UPI Payment')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">PayU UPI Payment</div>

                    <div class="card-body">
                        <p>You are being redirected to PayU for payment processing...</p>

                        <form id="payuForm" action="{{ $paymentUrl }}" method="post">
                            @foreach($data as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary" id="redirectButton">
                                    Click here if you are not redirected automatically
                                </button>
                            </div>
                        </form>
                        
                        <div id="timeoutMessage" class="alert alert-warning mt-3" style="display: none;">
                            <p><strong>Connection issue detected:</strong> The connection to PayU payment gateway timed out.</p>
                            <p>If you're trying to use the sandbox environment, this is a common issue. Please try:</p>
                            <ul>
                                <li>Clicking the button again</li>
                                <li>Checking if the PayU servers are currently available</li>
                                <li>If using test mode, consider switching to production mode by changing the URL in the PayUUPIMethod.php file</li>
                            </ul>
                            <p>Latest PayU sandbox URL: <code>https://test.payu.in/_payment</code> (try this if the current URL isn't working)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('payuForm');
            const timeoutMessage = document.getElementById('timeoutMessage');
            const redirectButton = document.getElementById('redirectButton');
            
            // Set a timeout to show error message if the form submission takes too long
            const timeoutId = setTimeout(function() {
                timeoutMessage.style.display = 'block';
            }, 10000); // Show message after 10 seconds
            
            // Submit the form automatically
            form.submit();
            
            // Add click handler to the button for manual submission
            redirectButton.addEventListener('click', function(event) {
                // Prevent the default action to handle manually
                event.preventDefault();
                
                // Hide timeout message if it's visible
                timeoutMessage.style.display = 'none';
                
                // Try submitting the form again
                form.submit();
                
                // Reset the timeout
                clearTimeout(timeoutId);
                setTimeout(function() {
                    timeoutMessage.style.display = 'block';
                }, 10000);
            });
        });
    </script>
@endsection