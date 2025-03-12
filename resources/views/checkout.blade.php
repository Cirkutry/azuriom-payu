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
                                <button type="submit" class="btn btn-primary">
                                    Click here if you are not redirected automatically
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('payuForm').submit();
        });
    </script>
@endsection
