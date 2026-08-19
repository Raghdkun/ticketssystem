@extends("errors.layout")

@section("code", "503")
@section("title", app()->getLocale() === "ar" ? "الموقع تحت الصيانة" : "Down for maintenance")
@section("heading", app()->getLocale() === "ar" ? "الموقع تحت الصيانة" : "Down for maintenance")
@section("message", app()->getLocale() === "ar" ? "نجري تحديثًا قصيرًا. عد بعد قليل." : "We are running a short update. Check back shortly.")
@section("cta", app()->getLocale() === "ar" ? "العودة إلى الرئيسية" : "Back to home")
