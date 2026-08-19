@extends("errors.layout")

@section("code", "419")
@section("title", app()->getLocale() === "ar" ? "انتهت صلاحية الجلسة" : "Your session expired")
@section("heading", app()->getLocale() === "ar" ? "انتهت صلاحية الجلسة" : "Your session expired")
@section("message", app()->getLocale() === "ar" ? "بقيت الصفحة مفتوحة مدة طويلة. حدّثها وحاول مجددًا." : "The page sat open too long. Refresh and try again.")
@section("cta", app()->getLocale() === "ar" ? "العودة إلى الرئيسية" : "Back to home")
