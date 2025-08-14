<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $paymentTitle ?? 'Organization Pre-Activated' }} - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="icon" type="image/png" href="{{asset('public/assets/images/Favicon.png')}}">
    <style>
        body {
            background: #f9f9f9;
            font-family: 'Segoe UI', sans-serif;
            --bs-gutter-x: 0 !important;
        }

        .header-logo{
            height: 70px;
            user-select: none;
        }

        .user-profile{
            user-select: none;
        }
        .main-container {
            margin-top: 70px;
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2rem;
            background-size: cover;
            background-position: center;
        }
        .left-panel {
            padding: 2rem;
            border-radius: 12px;
            max-width: 480px;
            user-select: none;
        }
        .left-panel > h2 > span {
            font-size: 1.7em;
            line-height: 0.85 !important;
            color: #054b8e;
        }

        .bg-blue{
            background-color: #054b8e;
        }
        .right-panel {
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .price-text {
            font-size: 2rem;
            color: #7e1a1a;
            font-weight: 700;
        }

        .text-maroon{
            color: #7e1a1a !important;
        }
        .btn-maroon, .btn-maroon:disabled {
            background-color: #7e1a1a;
            color: #fff;
        }
        .btn-blue {
            background-color: #054b8e;
            color: #fff;
        }
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                gap: 1.5rem;
            }

            .user-profile{
                scale: 0.88;
            }

            .header-logo{
                height: 58px;
            }
        }
    </style>
</head>
<body>
<!-- Absolute Navbar -->
<nav class="navbar navbar-expand navbar-light bg-white shadow-sm position-absolute top-0 start-0 w-100 z-3" style="height: 70px !important;">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand" href="#">
            <img src="{{ asset('/web/assets/assets/images/line_up_hero_header.png') }}" alt="Lineup Hero Logo" class="header-logo">
        </a>

        <!-- Navbar Links (right side) - Always Visible -->
        <div class="navbar-collapse d-flex justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item user-profile">
                    <div class="d-flex gap-2 align-items-center">
                        <img src="{{ asset('/web/assets/assets/images/dummy_image.png') }}" alt="Avatar Logo" style="width:40px;" class="bg-blue rounded-pill ">
                        <div class="d-flex flex-column m-0 p-0 justify-content-center">
                            <h6 style="line-height: 0.6" class="mt-1 mb-0 p-0 text-maroon">Welcome <span style="font-weight: 200 !important;">&#128075;</span></h6>
                            <p class="m-0 p-0">{{ isset($user->first_name) ? $user->first_name : $panel_name }}</p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-container container">
    <!-- Left Panel -->
    <div class="left-panel text-center">
        <h2>{{ $type == 'organization' ? 'RENEW' :  'PURCHASE' }} THE PREMIUM <span class="fw-bold">SUBSCRIPTION</span></h2> {{-- Changed Text --}}
        @if(isset($paymentDescription))
            <p class="payment-description px-3 text-secondary">{{ $paymentDescription }}</p>
        @endif
        {{--        <h4 class="mb-2 text-secondary">{{ config('app.name', 'Lineup Hero') }} Premium Access</h4> --}}{{-- Changed Text --}}

        {{-- Use variables passed from WebPaymentController --}}
        <div class="price-text my-5">
            @if($displayCurrencySymbolPosition === 'before')
                {{ $displayCurrencySymbol }}{{ $displayAmount }}
            @else
                {{ $displayAmount }}{{ $displayCurrencySymbol }}
            @endif
            {{ strtoupper($currency) }} / Year
        </div>

        <div class="border card rounded p-3 text-start">
            <div class="d-flex justify-content-between mb-2">
                <span>Annual Subscription</span>
                <strong>
                    @if($displayCurrencySymbolPosition === 'before')
                        {{ $displayCurrencySymbol }}{{ $displayAmount }}
                    @else
                        {{ $displayAmount }}{{ $displayCurrencySymbol }}
                    @endif
                </strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Subtotal</span>
                <strong>
                    @if($displayCurrencySymbolPosition === 'before')
                        {{ $displayCurrencySymbol }}{{ $displayAmount }}
                    @else
                        {{ $displayAmount }}{{ $displayCurrencySymbol }}
                    @endif
                </strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Tax (if applicable)</span> {{-- Changed for generality --}}
                <strong>$0.00</strong> {{-- Update if you calculate taxes --}}
            </div>
            <hr />
            <div class="d-flex justify-content-between">
                <strong>Total due today</strong>
                <strong>
                    @if($displayCurrencySymbolPosition === 'before')
                        {{ $displayCurrencySymbol }}{{ $displayAmount }}
                    @else
                        {{ $displayAmount }}{{ $displayCurrencySymbol }}
                    @endif
                </strong>
            </div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel w-100" style="max-width: 500px;">
        <form id="payment-form">





            <h5 class="text-maroon fw-bold mt-4 mb-3">PAYMENT METHOD</h5>
            <div class="mb-3">
                <!-- Stripe Payment Element mounts here -->
                <div id="payment-element" class="form-control d-flex justify-content-center align-items-center  bg-white" style="height: 400px;">
{{--                    <h5>Stripe Form</h5>--}}
                    <img src="{{asset('public/assets/images/stripe_form.png')}}" alt="">
                </div>
            </div>

            <div id="error-message" class="text-danger mb-3"></div>

            <button id="submit" class="btn btn-maroon w-100" type="submit">
                <span id="button-text">Subscribe Now</span>
                <span id="spinner" style="display: none;">Processing...</span>
            </button>
        </form>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade " id="paymentSuccessModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="paymentSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-header border-0">
                <h5 class="modal-title w-100 text-maroon" id="paymentSuccessModalLabel">{{$title}}</h5>
            </div>
            <div class="modal-body">
                <p class="mb-3">{{$messageBody}}</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="{{ $type == 'user' ? url('/web/#/main-dashboard-screen') : ($type == 'organization' ? url('/web/#/organization-dashboard-screen') : '#') }}" class="btn btn-blue">Go to Home</a>
                    {{--                    <button type="button" disabled class="btn disabled btn-maroon" data-bs-dismiss="modal">Close</button>--}}
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const stripeKey = "{{ $stripeKey ?? '' }}";         // Replace with test/public key directly for testing if needed
    const clientSecret = "{{ $clientSecret ?? '' }}";   // Inject this from your server
    const returnUrl = "{{ $returnUrl ?? '' }}";         // e.g., route('checkout.success')

    const form = document.getElementById('payment-form');
    const submitButton = document.getElementById('submit');
    const errorMessage = document.getElementById('error-message');
    const buttonText = document.getElementById('button-text');
    const spinner = document.getElementById('spinner');

    if (!stripeKey || !clientSecret || !returnUrl) {
        if (submitButton) submitButton.disabled = true;
    } else {
        try {
            const stripe = Stripe(stripeKey);
            const elements = stripe.elements({ clientSecret });

            const paymentElement = elements.create('payment');
            paymentElement.mount('#payment-element');

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                setLoading(true);
                errorMessage.textContent = '';

                const { error } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: returnUrl,
                    },
                });

                if (error) {
                    errorMessage.textContent = error.message;
                    setLoading(false);
                } else {
                    buttonText.textContent = 'Redirecting...';
                }
            });
        } catch (e) {
            console.error('Stripe initialization failed:', e);
            errorMessage.textContent = 'Could not initialize payment form. ' + e.message;
            if (submitButton) submitButton.disabled = true;
        }
    }

    function setLoading(isLoading) {
        if (!submitButton) return;
        submitButton.disabled = isLoading;
        spinner.style.display = isLoading ? 'inline' : 'none';
        buttonText.style.display = isLoading ? 'none' : 'inline';
    }
</script>


<script>
    window.addEventListener('DOMContentLoaded', () => {

        const successModal = new bootstrap.Modal(document.getElementById('paymentSuccessModal'));
        successModal.show();

    });
</script>
</body>
</html>
