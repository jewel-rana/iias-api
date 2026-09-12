<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\OrganizationSetting;
use Carbon\Carbon;

class MeetingAnnouncement
{
    public static function english(Meeting $meeting, ?OrganizationSetting $org = null): string
    {
        $org ??= OrganizationSetting::current();
        $when = Carbon::parse($meeting->starts_at)->timezone('Asia/Dhaka');
        $lines = [
            '*'.$org->organization_name.'*',
            $org->tagline,
            '',
            '*MEETING ANNOUNCEMENT*',
            '',
            'Assalamu Alaikum,',
            '',
            'A meeting of the organization has been scheduled.',
            '',
            '*Purpose:* '.$meeting->purpose,
            '*Date & Time:* '.$when->format('l, d M Y · h:i A'),
        ];
        $venue = self::venue($meeting, $org);
        if ($venue !== '') {
            $lines[] = '*Venue:* '.$venue;
        }
        $lines[] = '';
        $lines[] = 'Please attend on time.';
        $lines[] = '';
        $lines[] = 'Jazakumullahu Khairan';

        return implode("\n", $lines);
    }

    public static function bangla(Meeting $meeting, ?OrganizationSetting $org = null): string
    {
        $org ??= OrganizationSetting::current();
        $when = Carbon::parse($meeting->starts_at)->timezone('Asia/Dhaka');
        $lines = [
            '*'.$org->organization_name.'*',
            $org->tagline,
            '',
            '*সভার ঘোষণা*',
            '',
            'আসসালামু আলাইকুম,',
            '',
            'সংগঠনের একটি সভা আয়োজন করা হয়েছে।',
            '',
            '*উদ্দেশ্য:* '.$meeting->purpose,
            '*তারিখ ও সময়:* '.self::banglaDateTime($when),
        ];
        $venue = self::venue($meeting, $org);
        if ($venue !== '') {
            $lines[] = '*স্থান:* '.$venue;
        }
        $lines[] = '';
        $lines[] = 'অনুগ্রহ করে সময়মতো উপস্থিত থাকবেন।';
        $lines[] = '';
        $lines[] = 'জাযাকুমুল্লাহু খাইরান';

        return implode("\n", $lines);
    }

    private static function venue(Meeting $meeting, OrganizationSetting $org): string
    {
        $location = trim((string) $meeting->location);
        if ($location !== '') {
            return $location;
        }

        return trim((string) $org->address);
    }

    private static function banglaDateTime(Carbon $when): string
    {
        $weekdays = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];
        $months = [
            1 => 'জানুয়ারি',
            2 => 'ফেব্রুয়ারি',
            3 => 'মার্চ',
            4 => 'এপ্রিল',
            5 => 'মে',
            6 => 'জুন',
            7 => 'জুলাই',
            8 => 'আগস্ট',
            9 => 'সেপ্টেম্বর',
            10 => 'অক্টোবর',
            11 => 'নভেম্বর',
            12 => 'ডিসেম্বর',
        ];
        $hour24 = (int) $when->format('G');
        $period = match (true) {
            $hour24 < 5 => 'রাত',
            $hour24 < 12 => 'সকাল',
            $hour24 < 16 => 'দুপুর',
            $hour24 < 19 => 'বিকাল',
            $hour24 < 22 => 'সন্ধ্যা',
            default => 'রাত',
        };
        $hour12 = (int) $when->format('g');
        $minute = $when->format('i');
        $dayName = $weekdays[(int) $when->format('w')];
        $date = self::bnDigits($when->format('j')).' '.$months[(int) $when->format('n')].' '.self::bnDigits($when->format('Y'));
        $time = self::bnDigits($hour12.':'.$minute);

        return $dayName.', '.$date.', '.$period.' '.$time;
    }

    private static function bnDigits(string $value): string
    {
        return strtr($value, [
            '0' => '০',
            '1' => '১',
            '2' => '২',
            '3' => '৩',
            '4' => '৪',
            '5' => '৫',
            '6' => '৬',
            '7' => '৭',
            '8' => '৮',
            '9' => '৯',
        ]);
    }
}
