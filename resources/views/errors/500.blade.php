@extends("errors.layout")

@section("code", "500")
@section("title", app()->getLocale() === "ar" ? "حدث خطأ لدينا" : "Something broke on our side")
@section("heading", app()->getLocale() === "ar" ? "حدث خطأ لدينا" : "Something broke on our side")
@section("message", app()->getLocale() === "ar" ? "المشكلة من طرفنا وليست منك. تم تسجيلها ونعمل عليها." : "This is our fault, not yours. It has been logged.")
@section("cta", app()->getLocale() === "ar" ? "العودة إلى الرئيسية" : "Back to home")
