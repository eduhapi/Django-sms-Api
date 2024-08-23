from django import views
from . import views
from django.urls import path
from .views import send_sms,external_send_sms


urlpatterns = [
    path('send-sms/', send_sms, name='send_sms'),
    path('send-sms/', views.external_send_sms, name='external_send_sms'),
    
]
