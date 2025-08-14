<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormSubmissionMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response as HttpResponse;

class ContactController extends Controller
{
    use ApiResponseTrait;

    public function submitContactForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }
        $validatedData = $validator->validated();

        $settings = Settings::instance();
        // The recipient email should be a secure admin/support email address from your config or .env
        $recipientEmail = $settings->admin_notification_email ?? 'support@lineup-hero.com';
        if (empty($recipientEmail)) {
            Log::critical('Contact Us form submitted but no recipient email is configured.');
            return $this->errorResponse('Message could not be sent due to a configuration issue.', HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            Mail::to($recipientEmail)->send(new ContactFormSubmissionMail($validatedData));
            return $this->successResponse(null, 'Thank you for your message! We will get back to you shortly.');
        } catch (\Exception $e) {
            Log::error('Failed to send contact us email: ' . $e->getMessage(), ['data' => $validatedData]);
            return $this->errorResponse('Sorry, there was an error sending your message. Please try again later.', HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}