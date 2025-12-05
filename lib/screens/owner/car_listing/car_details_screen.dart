import 'package:flutter/material.dart';

class OwnerCarDetailsScreen extends StatelessWidget {
  const OwnerCarDetailsScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            // Top image section with back button, title, menu, and favorite icon
            Stack(
              children: [
                SizedBox(
                  height: 250,
                  width: double.infinity,
                  child: PageView(
                    children: [
                      Image.network(
                        'https://tesla-cdn.thron.com/delivery/public/image/tesla/6c7e41b9-7680-47e7-8f6e-74d0e1f4a6de/bvlatuR/std/1920x1080/_25-Hero-D',
                        fit: BoxFit.cover,
                      ),
                      Image.network(
                        'https://tesla-cdn.thron.com/delivery/public/image/tesla/edb2a27f-9c5d-4a7d-9f1e-3a5b1d1fc1a4/bvlatuR/std/1920x1080/_25-Hero-D',
                        fit: BoxFit.cover,
                      ),
                      Image.network(
                        'https://tesla-cdn.thron.com/delivery/public/image/tesla/8a91d9f0-6b77-4c5b-8a5e-5a5a1b01a2e5/bvlatuR/std/1920x1080/_25-Hero-D',
                        fit: BoxFit.cover,
                      ),
                    ],
                  ),
                ),
                Positioned(
                  top: 16,
                  left: 16,
                  child: CircleAvatar(
                    backgroundColor: Colors.white.withValues(alpha: 0.7),
                    child: IconButton(
                      icon: const Icon(Icons.arrow_back),
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                  ),
                ),
                Positioned(
                  top: 16,
                  right: 56,
                  child: CircleAvatar(
                    backgroundColor: Colors.white.withValues(alpha: 0.7),
                    child: IconButton(
                      icon: const Icon(Icons.more_vert),
                      onPressed: () {},
                    ),
                  ),
                ),
                Positioned(
                  top: 16,
                  right: 16,
                  child: CircleAvatar(
                    backgroundColor: Colors.white.withValues(alpha: 0.7),
                    child: IconButton(
                      icon: const Icon(Icons.favorite_border),
                      onPressed: () {},
                    ),
                  ),
                ),
                const Positioned(
                  top: 16,
                  left: 0,
                  right: 0,
                  child: Center(
                    child: Text(
                      'Car Details',
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 16,
                        color: Colors.black87,
                      ),
                    ),
                  ),
                ),
              ],
            ),

            // Details below image
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: ListView(
                  children: [
                    const Text(
                      'Tesla Model 3',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'A car with high specs that are rented at an affordable price.',
                      style: TextStyle(
                        fontSize: 14,
                        color: Colors.grey[600],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        const CircleAvatar(
                          backgroundColor: Colors.orange,
                          child: Text(
                            'HG',
                            style: TextStyle(color: Colors.white),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Row(
                              children: [
                                Text(
                                  'HAN Ghoibin',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                SizedBox(width: 4),
                                Icon(
                                  Icons.verified,
                                  color: Colors.blue,
                                  size: 16,
                                ),
                              ],
                            ),
                            Text(
                              'Owner',
                              style: TextStyle(
                                fontSize: 12,
                                color: Colors.grey[600],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),

                    const SizedBox(height: 24),

                    const Text(
                      'Car Features',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                      ),
                    ),

                    const SizedBox(height: 12),

                    Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: [
                        _featureCard(
                          icon: Icons.people,
                          title: 'Capacity',
                          value: '5 Seats',
                        ),
                        _featureCard(
                          icon: Icons.bolt,
                          title: 'Fuel Type',
                          value: 'Electric',
                        ),
                        _featureCard(
                          icon: Icons.speed,
                          title: 'Top Speed',
                          value: '210 Km/h',
                        ),
                        _featureCard(
                          icon: Icons.settings,
                          title: 'Transmission',
                          value: 'Automatic',
                        ),
                        _featureCard(
                          icon: Icons.straighten,
                          title: 'Length',
                          value: '4.69 m',
                        ),
                        _featureCard(
                          icon: Icons.local_parking,
                          title: 'Parking Assist',
                          value: 'Yes',
                        ),
                      ],
                    ),

                    const SizedBox(height: 24),

                    ElevatedButton(
                      onPressed: () {
                        // Edit car details logic here
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.black,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: const Text(
                        'Edit Car Details',
                        style: TextStyle(
                          fontSize: 16,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _featureCard({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Container(
      width: 110,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF5F5F7),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Icon(icon, size: 28, color: Colors.grey[600]),
          const SizedBox(height: 8),
          Text(
            title,
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey[600],
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontWeight: FontWeight.w600,
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }
}