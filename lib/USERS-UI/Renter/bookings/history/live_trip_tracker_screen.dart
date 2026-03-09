// Stub file for LiveTripTrackerScreen
import 'package:flutter/material.dart';
import 'package:cargo/USERS-UI/Renter/models/booking.dart';

class LiveTripTrackerScreen extends StatelessWidget {
  final int? bookingId;
  final Booking? booking;

  const LiveTripTrackerScreen({
    super.key,
    this.bookingId,
    this.booking,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Live Trip Tracker')),
      body: const Center(child: Text('Live Trip Tracker')),
    );
  }
}
