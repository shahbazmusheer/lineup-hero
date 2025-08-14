<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/png" href="{{asset('public/assets/images/Favicon.png')}}">
    <style>
        body {
            background: #f9f9f9;
            font-family: 'Segoe UI', sans-serif;
            --bs-gutter-x: 0 !important;
        }

        .alert-success, .alert-danger{
            display: none;
        }

        .alert p{
            margin: 0 !important;
            display: inline !important;
        }

        .text-maroon{
            color: #7e1a1a !important;
        }
        input:focus, textarea:focus {
            outline: 1px solid #7e1a1a !important;
            box-shadow: none !important;
            border:none !important;
        }
        .btn-maroon, .btn-maroon:disabled {
            background-color: #7e1a1a;
            color: #fff;
        }
        .btn-blue {
            background-color: #054b8e;
            color: #fff;
        }

        .header-logo{
            height: 70px;
            user-select: none;
        }

        .user-profile{
            user-select: none;
        }
        .main-container {
            margin-top: 170px;
            min-height: calc(100vh - 80px);
            --bs-gutter-x: 0;
            width: 100%;
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
            font-size: 1.8em;
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

        /*.text-maroon{*/
        /*    color: #7e1a1a !important;*/
        /*}*/
        .text-maroon {
            color: #8b3a3a !important;
        }
        .pay-btn {
            background-color: #7e1a1a;
            color: #fff;
        }

        .hero-section {
            height: 500px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to right, #2c3e50, #2e6d89);
            color: white;
            text-align: center;
            flex-direction: column;
        }

        .hero-section h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .hero-section .btn {
            font-weight: bold;
            padding: 10px 30px;
            margin: 0 2px;
        }

        .org-section{
            margin-top: 80px;
        }

        .org-section h1{
            color: #8b3a3a !important;
            margin-bottom: 20px;
            font-weight: bolder;
        }

        .org-section p{
            font-size: 17px !important;
        }
        .bg-dark-blue {
            background-color: #1e2c3a;
        }

        .footer-section a:hover {
            text-decoration: underline;
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
        <a class="navbar-brand" href="{{ config('app.url') }}">
            <img src="{{ asset('public/assets/images/line_up_hero_header.png') }}" alt="Lineup Hero Logo" class="header-logo">
        </a>

        <!-- Navbar Links (right side) - Always Visible -->
        <div class="navbar-collapse d-flex justify-content-end">
            <ul class="navbar-nav mb-2 mb-lg-0">
                <li class="nav-item user-profile">
                    <div class="d-flex gap-2 align-items-center">
                        <a href="{{ config('app.url') }}web/#/sign-in-screen" class="btn btn-dark py-2 px-3"><i class="fa fa-circle-user pe-3"></i>Log In</a>
                    </div>
                </li>
{{--                <li class="nav-item ms-2 user-profile">--}}
{{--                    <div class="d-flex gap-2 align-items-center">--}}
{{--                        <a href="{{ asset('public/assets/docs/User Guide - For Coaches & Managers - Lineup Hero.pdf') }}" download class="btn btn-outline-dark py-2 px-3"><i class="fa fa-book pe-3"></i>Docs</a>--}}
{{--                        <a href="{{ asset('public/assets/docs/User Guide - For Organization Admin - Lineup Hero.pdf') }}" download class="btn btn-outline-dark py-2 px-3"><i class="fa fa-book pe-3"></i>Docs</a>--}}
{{--                    </div>--}}
{{--                </li>--}}
                <li class="nav-item ms-2 user-profile">
                    <div class="dropdown">
                        <button class="btn btn-outline-dark py-2 px-3 dropdown-toggle" type="button" id="docsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-book pe-3"></i>Docs
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="docsDropdown" style="right: 0 !important; left: auto !important;">
                            <li>
                                <a class="dropdown-item" href="{{ asset('public/assets/docs/User Guide - For Coaches & Managers - Lineup Hero.pdf') }}" download>
                                    User Guide
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ asset('public/assets/docs/User Guide - For Organization Admin - Lineup Hero.pdf') }}" download>
                                    Organization Guide
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

            </ul>
        </div>
    </div>
</nav>

<div class="main-container container-fluid">
    <div class="hero-section">
        <h1>Game-Ready Lineup</h1>
        <div>
            <a href="{{ config('app.url') }}web/#/sign-in-screen" class="btn btn-light">Log In</a>
            <a href="{{ config('app.url') }}web/#/SignUpScreen" class="btn btn-light">Sign Up</a>
        </div>
        <p class="my-2">OR</p>
        <div>
            <a href="{{ config('app.url') }}web/#/OrganizationSignin" class="btn px-4 fw-light btn-outline-light fs-5">Login as Organization</a>
        </div>
    </div>
    <div class="container py-5">
        <h2 class="text-maroon fw-bold mb-4">How it works</h2>
        <p class="mb-5">Easily create lineups and track fair play over the course of the season</p>
        <div class="row text-center g-4">
            <div class="col-md-4">
                <div class="p-4 bg-white rounded shadow-sm h-100">
                    <img src="https://e40219c6eb28d12cb98a5c53dfa1ca21.cdn.bubble.io/cdn-cgi/image/w=256,h=256,f=auto,dpr=1,fit=contain/f1741466895252x293768529652089800/1.png" alt="Step 1" class="img-fluid mb-3" style="height: 220px;" />
                    <h5 class="text-maroon">Step 1</h5>
                    <p>Begin by entering the positions each player tends to play</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-white rounded shadow-sm h-100">
                    <img src="https://e40219c6eb28d12cb98a5c53dfa1ca21.cdn.bubble.io/cdn-cgi/image/w=256,h=256,f=auto,dpr=1,fit=contain/f1741466904602x734657644088636500/2.png" alt="Step 2" class="img-fluid mb-3" style="height: 220px;" />
                    <h5 class="text-maroon">Step 2</h5>
                    <p>Create fair lineup in seconds with our proprietary algorithm</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 bg-white rounded shadow-sm h-100">
                    <img src="https://e40219c6eb28d12cb98a5c53dfa1ca21.cdn.bubble.io/cdn-cgi/image/w=256,h=256,f=auto,dpr=1,fit=contain/f1741466911457x550850887894530200/3.png" alt="Step 3" class="img-fluid mb-3" style="height: 220px;" />
                    <h5 class="text-maroon">Step 3</h5>
                    <p>Print lineup to bring to game and save to track fair-play through the season</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Coaching & Organization Section -->
    <div class="container py-5">
        <div class="row">
            <div class="col-md-6 order-2 pt-md-0 pt-4 order-md-2">
                <h1 class="text-maroon fw-bolder">Want to Set Up an Organization?</h1>
                <div class="py-2">
                    <p>
                        If you're looking to create an organization, please note that this feature is available upon request.<br>

                        Reach out to the system administrator to get started with your organization setup.
                        We’ll create an organization for you and ensure everything is tailored to your needs.<br>

                        For assistance, please contact the admin via email <a href="mailto:{{$adminMail}}">{{$adminMail}}</a>.<br><br>
                        It’s recommended to specify your desired <b>Organization Name</b> in the email.
                    </p>
                </div>
                <a href="{{ config('app.url') }}web/#/OrganizationSignin" class="btn btn-dark px-4 mb-4">Login to Organization</a>
            </div>
            <div class="col-md-6 order-1 order-md-2">
                <img src="https://e40219c6eb28d12cb98a5c53dfa1ca21.cdn.bubble.io/cdn-cgi/image/w=768,h=512,f=auto,dpr=1,fit=contain/f1740709879396x887220206087416800/pexels-photo-1308713.jpeg" alt="Baseball" class="d-md-block ms-auto w-100 w-md-75 img-fluid rounded shadow-sm" />
            </div>
        </div>
    </div>

    <!-- ======= Contact Section ======= -->
    <section id="contact" class="contact section-bg">
        <div class="container" data-aos="fade-up">
            <div class="row">

                <div class="col-lg-6">

                    <div class="row">
                        <div class="col-md-12">
                            <div class="shadow-sm p-3 bg-white">

                                <h3 class="text-maroon fw-bolder">Contact Us</h3>
                                <p class="text-secondary">Have questions or need assistance? We're here to help – reach out to us anytime!</p>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="shadow-sm p-3 bg-white mt-4">
                                <h3 class="text-maroon">Email Us</h3>
                                <p class="text-secondary">{{$adminMail}}<br>You can also email us directly at <a href="mailto:{{$adminMail}}">{{$adminMail}}</a> – we’d love to hear from you!</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="col-lg-6">
                    <form id="contactForm" method="post" role="form" class="mt-md-0 mt-4 shadow-sm bg-white p-4">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <input type="text" name="name" class="form-control" id="name" placeholder="Your Name" required>
                            </div>
                            <div class="col-md-6 form-group mt-3 mt-md-0">
                                <input type="email" class="form-control" name="email" id="email" placeholder="Your Email" required>
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <input type="text" class="form-control" name="subject" id="subject" placeholder="Subject" required>
                        </div>
                        <div class="form-group mt-3">
                            <textarea class="form-control" name="message" rows="4" placeholder="Message" required></textarea>
                        </div>
                        <div class="alert mt-3 alert-success">
                            <strong>Success!</strong> <p id="contact-us-success"></p>
                        </div>
                        <div class="alert mt-3 alert-danger">
                            <strong>Error!</strong> <p class="p-0" id="contact-us-failed">This is testing...</p>
                        </div>
                        <div class="text-center"><button type="submit" class="btn btn-maroon mt-3 d-block ms-auto">Send Message</button></div>
                    </form>
                </div>

            </div>

        </div>
    </section><!-- End Contact Section -->
</div>
<footer class="footer-section bg-dark-blue mt-5 text-white py-4">
    <div class="container d-flex justify-content-between align-items-center flex-column flex-md-row text-center text-md-start">
        <!-- Left: Logo -->
        <div class="footer-logo mb-3 mb-md-0">
            <img src="{{ asset('public/assets/images/Lineup hero logo_LH White.png') }}" alt="Lineup Hero Logo" style="height: 40px;">
        </div>

        <!-- Right: Links -->
        <div class="footer-links d-flex gap-4">
            <a href="mailto:support@lineup-hero.com" class="text-white text-decoration-none">Contact us</a>
            <a href="{{ config('app.url') }}web/#/sign-in-screen" class="text-white text-decoration-none">Log in</a>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function () {
        $("form").on("submit", function (e) {
            e.preventDefault();

            let $form = $(this);
            let formData = {
                name: $form.find("input[name='name']").val(),
                email: $form.find("input[name='email']").val(),
                subject: $form.find("input[name='subject']").val(),
                message: $form.find("textarea[name='message']").val()
            };

            $.ajax({
                url: "{{ url('api/v1/contact-us') }}", // Replace with your actual route
                type: "POST",
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                success: function (response) {
                    $("#contact-us-success").text(response.message || "Message sent successfully!");
                    $(".alert-success").show();
                    $(".alert-danger").hide();
                    $form[0].reset(); // Reset form fields
                },
                error: function (xhr) {
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        let errors = xhr.responseJSON.errors;
                        let messages = Object.values(errors).flat().join('\n');

                        $("#contact-us-failed").text(messages);
                    } else {
                        $("#contact-us-failed").text("Something went wrong. Please try again later.");
                    }
                    $(".alert-danger").show();
                    $(".alert-success").hide();
                }
            });
        });
    });
</script>

</body>
</html>
