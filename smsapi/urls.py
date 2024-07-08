# project/urls.py
from django.contrib import admin
from django.urls import path, include  # Import include


urlpatterns = [
    path('admin/', admin.site.urls),
    path('', include('sms.urls')),  # Include your app's URLs and map the root URL
]

